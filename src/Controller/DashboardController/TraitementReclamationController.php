<?php

namespace App\Controller\DashboardController;

use App\Entity\Reclamation;
use App\Entity\TraitementReclamation;
use App\Form\TraitementReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\TraitementReclamationRepository;
use App\Service\AI\UrgencyDetectionService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/traitement')]
class TraitementReclamationController extends AbstractController
{
    private function checkRecruiter(): void
    {
        $user = $this->getUser();
        if (!$user || $user->getType() !== 'RECRUITER') {
            throw $this->createAccessDeniedException('Accès réservé aux recruteurs');
        }
    }

    #[Route('/reclamations', name: 'app_dashboard_traitement_reclamations', methods: ['GET'])]
    public function reclamations(
        ReclamationRepository $reclamationRepository,
        UrgencyDetectionService $ai
    ): Response {
        $this->checkRecruiter();

        $reclamations = $reclamationRepository->createQueryBuilder('r')
            ->where('r.statut != :statut OR r.statut IS NULL')
            ->setParameter('statut', 'Traité')
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();

        $analyzedReclamations = $ai->analyzeAndSortReclamations($reclamations);

        $urgencyStats = [
            'critique' => 0, 'elevee' => 0, 'moyenne' => 0, 'faible' => 0, 'minimale' => 0
        ];

        foreach ($analyzedReclamations as $item) {
            $level = $item['urgency_level'];
            switch($level) {
                case 'Critique': $urgencyStats['critique']++; break;
                case 'Élevée': $urgencyStats['elevee']++; break;
                case 'Moyenne': $urgencyStats['moyenne']++; break;
                case 'Faible': $urgencyStats['faible']++; break;
                default: $urgencyStats['minimale']++;
            }
        }

        $correctionFile = __DIR__ . '/../../../var/models/corrections.json';
        $correctionsCount = 0;
        if (file_exists($correctionFile)) {
            $corrections = json_decode(file_get_contents($correctionFile), true);
            $correctionsCount = is_array($corrections) ? count($corrections) : 0;
        }

        return $this->render('BackOffice/dashboard/traitement/reclamations.html.twig', [
            'reclamations_analyzed' => $analyzedReclamations,
            'urgency_stats' => $urgencyStats,
            'total' => count($analyzedReclamations),
            'corrections_count' => $correctionsCount
        ]);
    }

    #[Route('/traiter/{id}', name: 'app_dashboard_traitement_traiter', methods: ['GET', 'POST'])]
    public function traiter(
        Request $request,
        Reclamation $reclamation,
        EntityManagerInterface $em,
        UrgencyDetectionService $ai,
        MailService $mailService
    ): Response {
        $this->checkRecruiter();

        $user = $this->getUser();
        $urgency = $ai->detectUrgency($reclamation);

        $existingTraitement = $em->getRepository(TraitementReclamation::class)->findOneBy(['reclamation' => $reclamation]);

        if ($existingTraitement) {
            $this->addFlash('warning', 'Cette réclamation a déjà été traitée');
            return $this->redirectToRoute('app_dashboard_traitement_reclamations');
        }

        $traitement = new TraitementReclamation();
        $form = $this->createForm(TraitementReclamationType::class, $traitement);

        $traitement->setDateTraitement(new \DateTimeImmutable());
        $traitement->setReclamation($reclamation);
        $traitement->setReclamationId($reclamation->getId());
        $traitement->setAdminId($user->getId());
        $traitement->setUser($user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 🔥 Récupérer l'email directement depuis la requête
            $formData = $request->request->all();
            $emailDestinataire = $formData['traitement_reclamation']['emailDestinataire'] ?? null;

            // 🔥 DEBUG - Afficher l'email pour vérifier
            error_log('EMAIL DESTINATAIRE: ' . $emailDestinataire);

            // Correction IA
            $corrigerIa = $request->request->get('corriger_ia');
            if ($corrigerIa) {
                $niveauReel = (int)$request->request->get('niveau_reel', 50);
                $correctionFile = __DIR__ . '/../../../var/models/corrections.json';
                $corrections = file_exists($correctionFile) ? json_decode(file_get_contents($correctionFile), true) : [];
                $corrections[] = [
                    'text' => $reclamation->getObjet() . ' ' . $reclamation->getDescription(),
                    'score' => $niveauReel,
                    'date' => date('Y-m-d H:i:s')
                ];
                file_put_contents($correctionFile, json_encode($corrections));
                $this->addFlash('info', 'Correction enregistrée. L\'IA sera ré-entraînée.');
            }

            $reclamation->setStatut('Traité');
            $em->persist($traitement);
            $em->flush();

            // 🔥 ENVOI DE L'EMAIL
            if ($emailDestinataire) {
                try {
                    // Utiliser la même méthode que la commande de test
                    $transport = \Symfony\Component\Mailer\Transport::fromDsn('smtp://selim.benabdelkader@esprit.tn:jzjcvckzzlsvuasu@smtp.gmail.com:587');
                    $mailer = new \Symfony\Component\Mailer\Mailer($transport);

                    $emailContent = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #4472C4; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; border: 1px solid #ddd; }
                        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Carrieri</h1>
                            <p>Votre réclamation a été traitée</p>
                        </div>
                        <div class='content'>
                            <h2>Bonjour,</h2>
                            <p>Nous vous informons que votre réclamation a été traitée.</p>
                            <hr>
                            <p><strong>Objet :</strong> {$reclamation->getObjet()}</p>
                            <p><strong>Description :</strong> {$reclamation->getDescription()}</p>
                            <p><strong>Réponse :</strong> {$traitement->getReponseAdmin()}</p>
                            <hr>
                            <p>Cordialement,<br>L'équipe Carrieri</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " Carrieri - Tous droits réservés</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                    $email = (new \Symfony\Component\Mime\Email())
                        ->from('selim.benabdelkader@esprit.tn')
                        ->to($emailDestinataire)
                        ->subject('Votre réclamation a été traitée - Carrieri')
                        ->html($emailContent);

                    $mailer->send($email);

                    $this->addFlash('success', '✅ La réclamation a été traitée et un email a été envoyé à ' . $emailDestinataire);
                } catch (\Exception $e) {
                    $this->addFlash('danger', '❌ Erreur email: ' . $e->getMessage());
                    $this->addFlash('warning', '⚠️ La réclamation a été traitée mais l\'envoi d\'email a échoué.');
                }
            } else {
                $this->addFlash('warning', '⚠️ La réclamation a été traitée mais aucun email n\'a été saisi.');
            }

            return $this->redirectToRoute('app_dashboard_traitement_reclamations');
        }

        return $this->render('BackOffice/dashboard/traitement/traiter.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
            'urgency_score' => $urgency['score'],
            'urgency_level' => $urgency['niveau'],
        ]);
    }

    #[Route('/', name: 'app_dashboard_traitement_index', methods: ['GET'])]
    public function index(TraitementReclamationRepository $repository): Response
    {
        $this->checkRecruiter();

        $user = $this->getUser();
        $traitements = $repository->findBy(['user' => $user], ['dateTraitement' => 'DESC']);

        return $this->render('BackOffice/dashboard/traitement/index.html.twig', [
            'traitements' => $traitements,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_traitement_show', methods: ['GET'])]
    public function show(TraitementReclamation $traitement): Response
    {
        $this->checkRecruiter();

        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }

        return $this->render('BackOffice/dashboard/traitement/show.html.twig', [
            'traitement' => $traitement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TraitementReclamation $traitement, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();

        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce traitement');
        }

        $form = $this->createForm(TraitementReclamationType::class, $traitement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le traitement a été modifié avec succès');
            return $this->redirectToRoute('app_dashboard_traitement_index');
        }

        return $this->render('BackOffice/dashboard/traitement/edit.html.twig', [
            'form' => $form->createView(),
            'traitement' => $traitement,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_traitement_delete', methods: ['POST'])]
    public function delete(Request $request, TraitementReclamation $traitement, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();

        $user = $this->getUser();
        if ($traitement->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce traitement');
        }

        if ($this->isCsrfTokenValid('delete' . $traitement->getId(), $request->request->get('_token'))) {
            $reclamation = $traitement->getReclamation();
            if ($reclamation) {
                $reclamation->setStatut('En attente');
            }

            $em->remove($traitement);
            $em->flush();
            $this->addFlash('success', 'Le traitement a été supprimé avec succès');
        }

        return $this->redirectToRoute('app_dashboard_traitement_index');
    }
}