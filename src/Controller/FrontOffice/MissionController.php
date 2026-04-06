<?php
// src/Controller/FrontOffice/MissionController.php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Mission;
use App\Entity\User;
use App\Repository\MissionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat/missions')]
#[IsGranted('ROLE_CANDIDAT')]
class MissionController extends AbstractController
{
    #[Route('', name: 'app_candidate_mission')]
    public function index(Request $request, MissionRepository $missionRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        // Get filter parameters
        $type = $request->query->get('type');
        $search = $request->query->get('q');
        $sort = $request->query->get('sort', 'recent');

        // Get missions with filters
        $missions = $missionRepository->findAllWithFilters($type, $search, $sort);

        // Get all available types for the filter dropdown
        $allTypes = $missionRepository->findAllTypes();

        return $this->render('FrontOffice/main/mission.html.twig', [
            'missions' => $missions,
            'types' => $allTypes,
            'currentType' => $type,
            'currentSearch' => $search,
            'currentSort' => $sort,
        ]);
    }

    #[Route('/{id}', name: 'app_candidate_mission_detail')]
    public function show(Mission $mission): Response
    {
        return $this->render('FrontOffice/main/mission_detail.html.twig', [
            'mission' => $mission,
        ]);
    }
}