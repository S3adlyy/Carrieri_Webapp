<?php
// src/Controller/FrontOffice/MissionController.php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Repository\MissionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat/missions')]
#[IsGranted('ROLE_CANDIDAT')]
class MissionController extends AbstractController
{
    public function __construct(
        private MissionRepository $missionRepository
    ) {
    }

    #[Route('/', name: 'app_candidate_mission')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer toutes les missions disponibles pour les candidats
        $missions = $this->missionRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('FrontOffice/main/mission.html.twig', [
            'missions' => $missions,
        ]);
    }

    #[Route('/{id}', name: 'app_candidate_mission_show')]
    public function show(int $id): Response
    {
        $mission = $this->missionRepository->find($id);

        if (!$mission) {
            throw $this->createNotFoundException('Mission non trouvée');
        }

        return $this->render('FrontOffice/main/mission_show.html.twig', [
            'mission' => $mission,
        ]);
    }
}