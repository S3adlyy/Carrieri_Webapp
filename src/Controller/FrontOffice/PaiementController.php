<?php

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/paiement', name: 'paiement_')]
#[IsGranted('ROLE_CANDIDAT')]
class PaiementController extends AbstractController
{
    public function __construct(
        private PaiementService $paiementService,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Affiche la page de paiement pour un cours
     */
    #[Route('/cours/{id}', name: 'cours', methods: ['GET'])]
    public function paiementCours(int $id): Response
    {
        $cours = $this->entityManager->getRepository(Cours::class)->find($id);
        
        if (!$cours) {
            throw $this->createNotFoundException('Le cours n\'existe pas');
        }

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur a déjà accès au cours
        if ($this->paiementService->aCcesAuCours($user->getId(), $id)) {
            return $this->redirectToRoute('app_candidate_cours_show', ['id' => $id]);
        }

        // Les cours gratuits ne passent pas par Stripe
        if (!(bool) $cours->getEstPayant() || (float) ($cours->getPrix() ?? 0) <= 0) {
            return $this->redirectToRoute('app_candidate_cours_show', ['id' => $id]);
        }

        // Créer le PaymentIntent
        try {
            $clientSecret = $this->paiementService->creerPaymentIntent(
                (float) ($cours->getPrix() ?? 0),
                $cours->getTitre(),
                $id,
                $user->getId()
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du paiement: ' . $e->getMessage());
            return $this->redirectToRoute('app_candidate_cours');
        }

        return $this->render('FrontOffice/paiement/form.html.twig', [
            'cours' => $cours,
            'clientSecret' => $clientSecret,
            'publishableKey' => $this->paiementService->getPublishableKey(),
        ]);
    }

    /**
     * API endpoint pour confirmer le paiement
     */
    #[Route('/confirmer', name: 'confirmer', methods: ['POST'])]
    public function confirmerPaiement(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['paymentIntentId'], $data['coursId'])) {
            return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $coursId = (int)$data['coursId'];
            $paymentIntentId = $data['paymentIntentId'];

            // Récupérer le cours
            $cours = $this->entityManager->getRepository(Cours::class)->find($coursId);
            if (!$cours) {
                return $this->json(['error' => 'Cours non trouvé'], Response::HTTP_NOT_FOUND);
            }

            // Vérifier le PaymentIntent
            if (!$this->paiementService->verifyPaymentIntentOwnershipAndAmount(
                $paymentIntentId,
                $coursId,
                (int) $user->getId(),
                (float) ($cours->getPrix() ?? 0)
            )) {
                return $this->json(['error' => 'Paiement non confirmé'], Response::HTTP_PAYMENT_REQUIRED);
            }

            // Confirmer le paiement
            $achatCours = $this->paiementService->confirmerPaiement(
                $user->getId(),
                $coursId,
                $paymentIntentId,
                $cours->getPrix()
            );

            return $this->json([
                'success' => true,
                'message' => 'Paiement confirmé avec succès',
                'achatId' => $achatCours->getId()
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Page de succès après paiement
     */
    #[Route('/succes/{coursId}', name: 'succes', methods: ['GET'])]
    public function paiementSucces(Request $request, int $coursId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cours = $this->entityManager->getRepository(Cours::class)->find($coursId);
        if (!$cours) {
            throw $this->createNotFoundException('Cours non trouvé');
        }

        if (!$this->paiementService->aCcesAuCours($user->getId(), $coursId)) {
            $paymentIntentId = (string) $request->query->get('payment_intent', '');
            if ($paymentIntentId !== '' && (bool) $cours->getEstPayant()) {
                try {
                    if ($this->paiementService->verifyPaymentIntentOwnershipAndAmount(
                        $paymentIntentId,
                        $coursId,
                        (int) $user->getId(),
                        (float) ($cours->getPrix() ?? 0)
                    )) {
                        $this->paiementService->confirmerPaiement(
                            (int) $user->getId(),
                            $coursId,
                            $paymentIntentId,
                            (float) ($cours->getPrix() ?? 0)
                        );
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Le paiement a échoué: ' . $e->getMessage());
                    return $this->redirectToRoute('paiement_erreur');
                }
            }
        }

        // Vérifier que le paiement a été effectué
        if (!$this->paiementService->aCcesAuCours($user->getId(), $coursId)) {
            return $this->redirectToRoute('paiement_cours', ['id' => $coursId]);
        }

        return $this->render('FrontOffice/paiement/succes.html.twig', [
            'cours' => $cours,
        ]);
    }

    /**
     * Page d'erreur après paiement échoué
     */
    #[Route('/erreur', name: 'erreur', methods: ['GET'])]
    public function paiementErreur(): Response
    {
        return $this->render('FrontOffice/paiement/erreur.html.twig');
    }
}




