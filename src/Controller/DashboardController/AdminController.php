<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Repository\OffreEmploiRepository;
use App\Service\BackOfficeDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CertificateModerationService;
use App\Service\CertificationService;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private BackOfficeDashboardService $dashboardData,
        private OffreEmploiRepository $offreEmploiRepository,// Add this
        private CertificationService $certificationService,
        private CertificateModerationService $certificateModerationService,
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

    #[Route('/utilisateurs', name: 'app_admin_utilisateurs')]
    public function utilisateurs(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/utilisateurs/index.html.twig', [
            'users' => $this->dashboardData->listUsers($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
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

        // Récupérer les missions (comme avant)
        $missions = $this->dashboardData->listMissions($user);

        // Récupérer les statistiques
        $stats = $this->dashboardData->getMissionStats($user);

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $missions,
            'is_admin_view' => $this->dashboardData->isAdmin($user),
            'stats' => $stats,  // ← AJOUTEZ CETTE LIGNE
        ]);
    }

    #[Route('/offres-emploi', name: 'app_admin_offres_emploi')]
    public function offresEmploi(): Response
    {
        $user = $this->requireUser();

        // Get stats using the repository
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());
        $offres = $isAdmin
            ? $this->offreEmploiRepository->findBy([], ['id' => 'DESC'])
            : $this->offreEmploiRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $total = count($offres);
        $actives = 0;
        $expirees = 0;
        $today = new \DateTime();
        $parContrat = ['CDI' => 0, 'CDD' => 0, 'Stage' => 0, 'Freelance' => 0];

        foreach ($offres as $offre) {
            if ($offre->getDateExpiration() && $offre->getDateExpiration() > $today) {
                $actives++;
            } else {
                $expirees++;
            }

            $type = $offre->getTypeContrat();
            if ($type && isset($parContrat[$type])) {
                $parContrat[$type]++;
            }
        }

        $stats = [
            'total' => $total,
            'actives' => $actives,
            'expirees' => $expirees,
            'par_contrat' => $parContrat,
        ];

        // Add empty filters array for the search form
        $filters = [
            'keyword' => '',
            'typeContrat' => '',
            'statut' => '',
            'salaireMin' => '',
        ];

        return $this->render('BackOffice/dashboard/offres_emploi/index.html.twig', [
            'offres' => $offres,
            'is_admin_view' => $isAdmin,
            'stats' => $stats,
            'filters' => $filters,  // ← Add this line
        ]);
    }

    #[Route('/certifications', name: 'app_admin_certifications')]
    public function certifications(): Response
    {
        $user = $this->requireUser();
        $certifications = $this->dashboardData->listCertifications($user);
        $certificateStates = [];

        foreach ($certifications as $certification) {
            $id = $certification->getId();
            if ($id === null) {
                continue;
            }
            $certificateStates[$id] = $this->certificateModerationService->getCertificateState($certification);
        }

        return $this->render('BackOffice/dashboard/certifications/index.html.twig', [
            'certifications' => $certifications,
            'certificate_states' => $certificateStates,
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
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

    #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function reclamations(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/reclamations/index.html.twig', [
            'reclamations' => $this->dashboardData->listReclamations($user),
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

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
    private function canModerateCertificate(User $user, \App\Entity\Certification $certificate): bool
    {
        if ($this->dashboardData->isAdmin($user)) {
            return true;
        }

        return $certificate->getCours()?->getUser()?->getId() === $user->getId();
    }
}