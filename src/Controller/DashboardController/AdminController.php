<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Form\UserEditType;
use App\Service\BackOfficeDashboardService;
use App\Service\CertificateModerationService;
use App\Service\CertificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\RenduMissionRepository;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private BackOfficeDashboardService $dashboardData,
        private RenduMissionRepository $renduMissionRepository,
        private CertificationService $certificationService,
        private CertificateModerationService $certificateModerationService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->requireUser();

        $stats = $this->dashboardData->getStats($user);
        $recent_users = $this->dashboardData->getRecentUsersPreview($user, 5);
        $recent_activities = $this->dashboardData->getRecentMissionActivity($user, 5);

        return $this->render('BackOffice/dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_users' => $recent_users,
            'recent_activities' => $recent_activities,
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/messages', name: 'app_admin_messages')]
    public function messages(): Response
    {
        $user = $this->requireUser();
        return $this->render('BackOffice/dashboard/messages/index.html.twig', [
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/utilisateurs', name: 'app_admin_utilisateurs')]
    public function utilisateurs(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/utilisateurs/index.html.twig', [
            'users' => $this->dashboardData->listUsers($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/utilisateur/{id}/edit', name: 'app_admin_utilisateur_edit')]
    public function editUtilisateur(Request $request, User $user): Response
    {
        $this->requireUser();

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }

        return $this->render('BackOffice/dashboard/utilisateurs/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/utilisateur/{id}/delete', name: 'app_admin_utilisateur_delete', methods: ['POST'])]
    public function deleteUtilisateur(Request $request, User $user): Response
    {
        $this->requireUser();

        // Prevent admin from deleting themselves
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }

        if ($this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            try {
                // Delete related records based on user type
                $connection = $this->entityManager->getConnection();
                $userId = $user->getId();

                // Delete workspace entries
                $connection->executeStatement('DELETE FROM workspace WHERE candidat_id = ?', [$userId]);

                // Delete postulations
                $connection->executeStatement('DELETE FROM entretien WHERE postulation_id IN (SELECT id FROM postulation WHERE user_id = ?)', [$userId]);
                $connection->executeStatement('DELETE FROM postulation WHERE user_id = ?', [$userId]);

                // Delete favorites
                $connection->executeStatement('DELETE FROM favorites_offres WHERE user_id = ?', [$userId]);

                // Delete messages
                $connection->executeStatement('DELETE FROM message WHERE expediteur_id = ? OR destinataire_id = ?', [$userId, $userId]);

                // Delete conversations
                $connection->executeStatement('DELETE FROM conversation WHERE user1_id = ? OR user2_id = ?', [$userId, $userId]);

                // Delete missions submissions
                $connection->executeStatement('DELETE FROM rendu_mission WHERE user_id = ?', [$userId]);

                // Delete certifications
                $connection->executeStatement('DELETE FROM certification WHERE user_id = ?', [$userId]);

                // Delete reclamations
                $connection->executeStatement('DELETE FROM reclamation WHERE user_id = ?', [$userId]);

                // Delete courses if user is a teacher
                if ($user->getType() === 'RECRUITER' || $user->getType() === 'ADMIN') {
                    $connection->executeStatement('UPDATE cours SET user_id = NULL WHERE user_id = ?', [$userId]);
                }

                // Delete offers if user is a recruiter
                if ($user->getType() === 'RECRUITER') {
                    $offers = $this->entityManager->getRepository(OffreEmploi::class)->findBy(['user' => $user]);
                    foreach ($offers as $offer) {
                        // Delete related postulations for each offer
                        $connection->executeStatement('DELETE FROM entretien WHERE postulation_id IN (SELECT id FROM postulation WHERE offre_id = ?)', [$offer->getId()]);
                        $connection->executeStatement('DELETE FROM postulation WHERE offre_id = ?', [$offer->getId()]);
                    }
                    $connection->executeStatement('DELETE FROM offre_emploi WHERE user_id = ?', [$userId]);
                }

                // Finally delete the user
                $this->entityManager->remove($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Utilisateur supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_utilisateurs');
    }

    #[Route('/cours', name: 'app_admin_cours')]
    public function cours(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/cours/index.html.twig', [
            'cours_list' => $this->dashboardData->listCours($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/missions', name: 'app_admin_missions')]
    public function missions(): Response
    {
        $user = $this->requireUser();

        $missions = $this->dashboardData->listMissions($user);
        $stats = $this->getMissionStats($user);

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $missions,
            'is_admin_view' => $this->dashboardData->isAdmin($user),
            'stats' => $stats,
        ]);
    }

    private function getMissionStats(User $user): array
    {
        $missions = $this->dashboardData->listMissions($user);

        $totalMissions = count($missions);
        $totalSubmissions = 0;
        $totalAccepted = 0;
        $totalRejected = 0;
        $totalPending = 0;
        $scores = [];
        $missionsWithSubmissions = 0;

        foreach ($missions as $mission) {
            $submissions = $this->renduMissionRepository->findBy(['missionId' => $mission->getId()]);
            $submissionCount = count($submissions);
            $totalSubmissions += $submissionCount;

            if ($submissionCount > 0) {
                $missionsWithSubmissions++;
            }

            foreach ($submissions as $submission) {
                if ($submission->getScore()) {
                    $scores[] = $submission->getScore();
                }

                if ($submission->getStatut() === 'accepte') {
                    $totalAccepted++;
                } elseif ($submission->getStatut() === 'refuse') {
                    $totalRejected++;
                } elseif ($submission->getStatut() === 'en_attente') {
                    $totalPending++;
                }
            }
        }

        $averageScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;
        $maxScore = !empty($scores) ? max($scores) : 0;
        $minScore = !empty($scores) ? min($scores) : 0;

        $scoreDistribution = [
            'excellent' => 0,
            'good' => 0,
            'average' => 0,
            'poor' => 0,
            'very_poor' => 0,
        ];

        foreach ($scores as $score) {
            if ($score >= 90) $scoreDistribution['excellent']++;
            elseif ($score >= 75) $scoreDistribution['good']++;
            elseif ($score >= 50) $scoreDistribution['average']++;
            elseif ($score >= 25) $scoreDistribution['poor']++;
            else $scoreDistribution['very_poor']++;
        }

        return [
            'total_missions' => $totalMissions,
            'total_submissions' => $totalSubmissions,
            'missions_with_submissions' => $missionsWithSubmissions,
            'submission_rate' => $totalMissions > 0 ? round(($missionsWithSubmissions / $totalMissions) * 100) : 0,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'total_pending' => $totalPending,
            'acceptance_rate' => $totalSubmissions > 0 ? round(($totalAccepted / $totalSubmissions) * 100) : 0,
            'average_score' => round($averageScore, 1),
            'max_score' => round($maxScore, 1),
            'min_score' => round($minScore, 1),
            'score_distribution' => $scoreDistribution,
        ];
    }

    #[Route('/offres-emploi', name: 'app_admin_offres_emploi')]
    public function offresEmploi(): Response
    {
        return $this->redirectToRoute('app_admin_offres_list');
    }

    #[Route('/certifications', name: 'app_admin_certifications')]
    public function certifications(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/certifications/index.html.twig', [
            'certifications' => $this->dashboardData->listCertifications($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function reclamations(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/reclamations/index.html.twig', [
            'reclamations' => $this->dashboardData->listReclamations($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    #[Route('/certifications/{id}/invalider', name: 'app_admin_certification_invalidate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function invalidateCertification(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        $certificate = $this->certificationService->getCertificate($id);
        if ($certificate === null) {
            $this->addFlash('danger', 'Certification introuvable.');
            return $this->redirectToRoute('app_admin_certifications');
        }

        if (!$this->canModerateCertificate($user, $certificate)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('invalidate_cert_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_certifications');
        }

        $reason = (string) $request->request->get('reason', '');
        $this->certificateModerationService->markInvalid($certificate, $user, $reason);
        $this->addFlash('success', 'Certificat marque comme non valide (fraude).');

        return $this->redirectToRoute('app_admin_certifications');
    }

    private function canModerateCertificate(User $user, \App\Entity\Certification $certificate): bool
    {
        if ($this->dashboardData->isAdmin($user)) {
            return true;
        }

        return $certificate->getCours()?->getUser()?->getId() === $user->getId();
    }

    #[Route('/certifications/{id}/valider', name: 'app_admin_certification_validate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function validateCertification(Request $request, int $id): Response
    {
        $user = $this->requireUser();
        $certificate = $this->certificationService->getCertificate($id);
        if ($certificate === null) {
            $this->addFlash('danger', 'Certification introuvable.');
            return $this->redirectToRoute('app_admin_certifications');
        }

        if (!$this->canModerateCertificate($user, $certificate)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('validate_cert_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_certifications');
        }

        $this->certificateModerationService->markValid($certificate, $user);
        $this->addFlash('success', 'Certificat valide confirme.');

        return $this->redirectToRoute('app_admin_certifications');
    }
}