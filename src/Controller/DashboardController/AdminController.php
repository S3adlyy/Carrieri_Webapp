<?php

namespace App\Controller\DashboardController;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'users' => 1542,
            'cours' => 53,
            'missions' => 44,
            'offres' => 32,
        ];

        $recent_users = [
            ['nom' => 'Jean Dupont', 'email' => 'jean@example.com', 'date' => new \DateTime('2024-01-15')],
            ['nom' => 'Marie Martin', 'email' => 'marie@example.com', 'date' => new \DateTime('2024-01-14')],
            ['nom' => 'Pierre Durand', 'email' => 'pierre@example.com', 'date' => new \DateTime('2024-01-13')],
        ];

        $recent_activities = [
            ['user' => 'Jean Dupont', 'action' => 'Nouvelle inscription', 'date' => new \DateTime('2024-01-15 10:30:00')],
            ['user' => 'Marie Martin', 'action' => 'A complété un cours', 'date' => new \DateTime('2024-01-15 09:15:00')],
            ['user' => 'Pierre Durand', 'action' => 'A soumis une mission', 'date' => new \DateTime('2024-01-14 14:45:00')],
        ];

        return $this->render('BackOffice/dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_users' => $recent_users,
            'recent_activities' => $recent_activities,
        ]);
    }

    #[Route('/utilisateurs', name: 'app_admin_utilisateurs')]
    public function utilisateurs(): Response
    {
        return $this->render('BackOffice/dashboard/utilisateurs/index.html.twig');
    }

    #[Route('/cours', name: 'app_admin_cours')]
    public function cours(): Response
    {
        return $this->render('BackOffice/dashboard/cours/index.html.twig');
    }

    #[Route('/missions', name: 'app_admin_missions')]
    public function missions(): Response
    {
        return $this->render('BackOffice/dashboard/missions/index.html.twig');
    }

    #[Route('/offres-emploi', name: 'app_admin_offres_emploi')]
    public function offresEmploi(): Response
    {
        return $this->render('BackOffice/dashboard/offres_emploi/index.html.twig');
    }

    #[Route('/certifications', name: 'app_admin_certifications')]
    public function certifications(): Response
    {
        return $this->render('BackOffice/dashboard/certifications/index.html.twig');
    }

    #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function reclamations(): Response
    {
        return $this->render('BackOffice/dashboard/reclamations/index.html.twig');
    }
}