<?php
// src/Controller/FrontOffice/RenduMissionController.php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\RenduMission;
use App\Entity\User;
use App\Repository\MissionRepository;
use App\Repository\RenduMissionRepository;
use App\Service\AiCodeEvaluatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat/rendu-mission')]
#[IsGranted('ROLE_CANDIDAT')]
class RenduMissionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionRepository $missionRepository,
        private RenduMissionRepository $renduMissionRepository,
        private AiCodeEvaluatorService $aiEvaluator,
    ) {}

    // ── /mes-resultats must be declared BEFORE /{id} ────────────────────────

    #[Route('/mes-resultats', name: 'app_candidate_my_results')]
    public function myResults(): Response
    {
        $user = $this->requireUser();

        $soumissions = $this->renduMissionRepository->findBy(
            ['candidatId' => $user->getId()],
            ['dateRendu' => 'DESC']
        );

        return $this->render('FrontOffice/main/my_results.html.twig', [
            'soumissions' => $soumissions,
        ]);
    }

    #[Route('/status/{id}', name: 'app_candidate_rendu_status')]
    public function status(int $id): Response
    {
        $user = $this->requireUser();

        $rendu = $this->renduMissionRepository->find($id);
        if (!$rendu || $rendu->getUser() !== $user) {
            throw $this->createNotFoundException('Soumission non trouvée');
        }

        return $this->render('FrontOffice/main/rendu_status.html.twig', [
            'rendu'   => $rendu,
            'mission' => $rendu->getMission(),
        ]);
    }

    #[Route('/{id}', name: 'app_candidate_rendu_mission')]
    public function index(int $id, Request $request): Response
    {
        $user    = $this->requireUser();
        $mission = $this->missionRepository->find($id)
            ?? throw $this->createNotFoundException('Mission non trouvée');

        $existingRendu = $this->renduMissionRepository->findExistingSubmission(
            $mission->getId(),
            $user->getId()
        );

        if ($request->isMethod('POST')) {
            $code   = (string) $request->request->get('code', '');
            $langue = (string) $request->request->get('langue', 'javascript');

            // ── Call AI evaluation API ────────────────────────────────────
            $evaluation = $this->aiEvaluator->evaluate(
                code:               $code,
                language:           $langue,
                missionDescription: $mission->getDescription() ?? '',
                missionTitle:       $mission->getType() ?? 'Mission #'.$mission->getId(),
                scoreMin:           $mission->getScoreMin() ?? 60,
            );

            $renduMission = new RenduMission();
            $renduMission->setCodeSolution($code);
            $renduMission->setLangue($langue);
            $renduMission->setDateRendu(new \DateTime());
            $renduMission->setScore($evaluation['score']);
            $renduMission->setResultat($evaluation['resultat_html']);
            $renduMission->setFeedback($evaluation['feedback']);
            $renduMission->setStatut($evaluation['statut']);   // 'accepte' or 'refuse'
            $renduMission->setMission($mission);
            $renduMission->setUser($user);

            $this->entityManager->persist($renduMission);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre solution a été soumise et évaluée par l\'IA !');

            return $this->redirectToRoute('app_candidate_rendu_status', [
                'id' => $renduMission->getId(),
            ]);
        }

        return $this->render('FrontOffice/main/rendu_mission.html.twig', [
            'mission'       => $mission,
            'existingRendu' => $existingRendu,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }
}
