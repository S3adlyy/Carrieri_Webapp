<?php
// src/Controller/DashboardController/RecruiterDashboardController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\User;
use App\Repository\OffreEmploiRepository;
use App\Repository\PostulationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recruteur')]
#[IsGranted('ROLE_RECRUITER')]
class RecruiterDashboardController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private OffreEmploiRepository $offreEmploiRepository,
        private PostulationRepository $postulationRepository,
    ) {
    }

    #[Route('/dashboard', name: 'app_recruiter_dashboard')]
    public function dashboard(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Statistiques des offres
        $offreStats = $this->offreEmploiRepository->getStatsForUser($user);
        
        // Statistiques des postulations
        $postulationStats = $this->postulationRepository->getDetailedStatsForRecruiter($user);
        
        // Évolution des candidatures (6 derniers mois)
        $evolution = $this->postulationRepository->getPostulationsEvolution($user, 6);
        
        // Dernières candidatures reçues
        $recentPostulations = $this->postulationRepository->findByRecruiter($user);
        $recentPostulations = array_slice($recentPostulations, 0, 5);
        
        // Top offres avec le plus de candidatures
        $topOffres = array_slice($postulationStats['by_offre'], 0, 5);
        
        // Offres actives
        $activeOffres = $this->offreEmploiRepository->findByUserWithSearch($user, '');
        $activeOffres = array_filter($activeOffres, function($offre) {
            return $offre->getDateExpiration() && $offre->getDateExpiration() > new \DateTime();
        });
        $activeOffres = array_slice($activeOffres, 0, 5);
        
        // Taux de conversion (acceptées / total)
        $conversionRate = $postulationStats['total'] > 0 
            ? round(($postulationStats['accepted'] / $postulationStats['total']) * 100, 1)
            : 0;

        return $this->render('BackOffice/dashboard/recruiter/dashboard.html.twig', [
            'offreStats' => $offreStats,
            'postulationStats' => $postulationStats,
            'evolution' => $evolution,
            'recentPostulations' => $recentPostulations,
            'topOffres' => $topOffres,
            'activeOffres' => $activeOffres,
            'conversionRate' => $conversionRate,
        ]);
    }
}
