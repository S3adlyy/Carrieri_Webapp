<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\UserTypeCasterTrait;
use App\Entity\Reclamation;
use App\Entity\User;
use App\Service\MailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mail', name: 'api_mail_')]
class MailApiController extends AbstractController
{
    use UserTypeCasterTrait;

    public function __construct(
        private MailService $mailService,
        private MailerInterface $mailer
    ) {}

    /**
     * API: Envoyer un email au candidat après traitement
     * POST /api/mail/reclamation/{id}/notify
     */
    #[Route('/reclamation/{id}/notify', name: 'send_reclamation_notification', methods: ['POST'])]
    public function sendReclamationNotification(Reclamation $reclamation, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User || ($user->getType() !== 'ADMIN' && $user->getType() !== 'RECRUITER')) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $reponse = $data['reponse'] ?? '';
            $statutFinal = $data['statut_final'] ?? 'Résolu';
            $reclamationUser = $reclamation->getUser();
            $emailDestinataire = $reclamationUser?->getEmail();
            $prenom = $reclamationUser?->getFirstName();

            if (empty($reponse)) {
                return $this->json(['error' => 'La réponse est obligatoire'], Response::HTTP_BAD_REQUEST);
            }

            $this->mailService->sendReclamationTreatedEmail($reclamation, $reponse, $statutFinal, $emailDestinataire, $prenom);

            return $this->json([
                'success' => true,
                'message' => 'Email envoyé avec succès',
                'to' => $emailDestinataire
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'envoi',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API: Envoyer un email de confirmation de réclamation
     * POST /api/mail/reclamation/{id}/confirm
     */
    #[Route('/reclamation/{id}/confirm', name: 'send_reclamation_confirmation', methods: ['POST'])]
    public function sendReclamationConfirmation(Reclamation $reclamation): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User || $user->getType() !== 'CANDIDATE') {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }

        if ($reclamation->getUser() !== $user) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'message' => 'Email de confirmation envoyé'
        ], Response::HTTP_OK);
    }

    /**
     * API: Tester l'envoi d'email
     * POST /api/mail/test
     */
    #[Route('/test', name: 'test_mail', methods: ['POST'])]
    public function testMail(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $email = $data['email'] ?? null;

            if (!$email) {
                $user = $this->getAuthenticatedUser();
                $email = $user instanceof User ? $user->getEmail() : null;
            }

            if (!$email) {
                return $this->json(['error' => 'No email provided'], Response::HTTP_BAD_REQUEST);
            }

            $testEmail = (new \Symfony\Component\Mime\Email())
                ->from('noreply@carrieri.com')
                ->to($email)
                ->subject('Test - Carrieri API Mail')
                ->html('<h1>Test réussi !</h1><p>Votre API d\'envoi d\'emails fonctionne correctement.</p>');

            $this->mailer->send($testEmail);

            return $this->json(['success' => true, 'message' => 'Email de test envoyé à ' . $email]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
