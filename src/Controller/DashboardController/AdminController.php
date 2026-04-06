<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Service\BackOfficeDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private BackOfficeDashboardService $dashboardData,
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

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $this->dashboardData->listMissions($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
    }

    #[Route('/offres-emploi', name: 'app_admin_offres_emploi')]
    public function offresEmploi(): Response
    {
        $user = $this->requireUser();

        return $this->render('BackOffice/dashboard/offres_emploi/index.html.twig', [
            'offres' => $this->dashboardData->listOffresEmploi($user),
            'is_admin_view' => $this->dashboardData->isAdmin($user),
        ]);
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
}
