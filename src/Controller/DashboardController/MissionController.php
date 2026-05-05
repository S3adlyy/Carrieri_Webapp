<?php
// src/Controller/DashboardController/MissionController.php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Entity\Mission;
use App\Entity\User;
use App\Form\MissionType;
use App\Repository\MissionRepository;
use App\Repository\RenduMissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin/missions')]
class MissionController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionRepository $missionRepository,
        private RenduMissionRepository $renduMissionRepository
    ) {
    }

    #[Route('/', name: 'app_admin_missions_list')]
    #[IsGranted('ROLE_RECRUITER')]
    public function index(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les paramètres de recherche et de tri
        $searchString = trim((string) $request->query->get('search', ''));
        $sortByString = trim((string) $request->query->get('sort_by', 'id'));
        $sortOrderString = trim((string) $request->query->get('sort_order', 'DESC'));
        $filter = trim((string) $request->query->get('filter', 'all'));

        // Valider les paramètres de tri
        $allowedSortFields = ['id', 'description', 'type', 'scoreMin', 'createdAt'];
        if (!in_array($sortByString, $allowedSortFields, true)) {
            $sortByString = 'id';
        }
        $sortOrderString = strtoupper($sortOrderString) === 'ASC' ? 'ASC' : 'DESC';

        // Récupérer les missions avec recherche et tri
        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $searchString,
            $sortByString,
            $sortOrderString
        );

        // Appliquer le filtre par score
        if ($filter !== 'all') {
            $missions = array_filter($missions, function (Mission $mission) use ($filter): bool {
                $score = $mission->getScoreMin();
                if ($filter === 'high') {
                    return $score >= 80;
                } elseif ($filter === 'medium') {
                    return $score >= 50 && $score <= 79;
                } elseif ($filter === 'low') {
                    return $score < 50;
                }
                return true;
            });
        }

        // Récupérer les statistiques
        $stats = $this->getMissionStats($user);

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $missions,
            'is_admin_view' => false,
            'search' => $searchString,
            'sort_by' => $sortByString,
            'sort_order' => $sortOrderString,
            'filter' => $filter,
            'stats' => $stats,  // ← TRÈS IMPORTANT : Cette ligne doit être présente
        ]);
    }

    #[Route('/export/excel', name: 'app_admin_missions_export_excel')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportExcel(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $searchString = trim((string) $request->query->get('search', ''));
        $sortByString = trim((string) $request->query->get('sort_by', 'id'));
        $sortOrderString = trim((string) $request->query->get('sort_order', 'DESC'));

        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $searchString,
            $sortByString,
            $sortOrderString
        );

        $html = $this->renderView('BackOffice/dashboard/missions/export_excel.html.twig', [
            'missions' => $missions,
            'export_date' => date('d/m/Y H:i:s'),
            'search' => $searchString,
        ]);

        $fileName = 'missions_' . date('Y-m-d_H-i-s') . '.xls';

        return new Response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    #[Route('/export/pdf', name: 'app_admin_missions_export_pdf')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportPDF(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $searchString = trim((string) $request->query->get('search', ''));
        $sortByString = trim((string) $request->query->get('sort_by', 'id'));
        $sortOrderString = trim((string) $request->query->get('sort_order', 'DESC'));

        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $searchString,
            $sortByString,
            $sortOrderString
        );

        $html = $this->renderView('BackOffice/dashboard/missions/export_pdf.html.twig', [
            'missions' => $missions,
            'export_date' => date('d/m/Y H:i:s'),
            'search' => $searchString,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $fileName = 'missions_' . date('Y-m-d_H-i-s') . '.pdf';
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }

    #[Route('/create', name: 'app_admin_missions_create')]
    #[IsGranted('ROLE_RECRUITER')]
    public function create(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mission->setUser($user);
            $mission->setCreatedAt(new \DateTime());
            $userId = $user->getId();
            if ($userId) {
                $mission->setCreatedById($userId);
            }

            $this->entityManager->persist($mission);
            $this->entityManager->flush();

            $this->addFlash('success', 'La mission a été créée avec succès.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/create.html.twig', [
            'form' => $form->createView(),
            'mission' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_missions_edit')]
    #[IsGranted('ROLE_RECRUITER')]
    public function edit(Request $request, Mission $mission): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La mission a été modifiée avec succès.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/edit.html.twig', [
            'form' => $form->createView(),
            'mission' => $mission,
        ]);
    }

    #[Route('/{id}/show', name: 'app_admin_missions_show')]
    #[IsGranted('ROLE_RECRUITER')]
    public function show(Mission $mission): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($mission->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('error', 'Vous ne pouvez pas voir cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/show.html.twig', [
            'mission' => $mission,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_missions_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function delete(Request $request, Mission $mission): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $tokenRaw = $request->request->get('_token');
        $token = is_string($tokenRaw) ? $tokenRaw : '';
        if ($this->isCsrfTokenValid('delete' . $mission->getId(), $token)) {
            $this->entityManager->remove($mission);
            $this->entityManager->flush();
            $this->addFlash('success', 'La mission a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_missions_list');
    }

    #[Route('/{id}/submissions', name: 'app_admin_mission_submissions')]
    #[IsGranted('ROLE_RECRUITER')]
    public function submissions(int $id, Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = $this->missionRepository->find($id);
        if (!$mission || $mission->getUser() !== $user) {
            $this->addFlash('error', 'Mission non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $rendus = $this->renduMissionRepository->findBy(
            ['missionId' => $mission->getId()],
            ['dateRendu' => 'DESC']
        );

        return $this->render('BackOffice/dashboard/missions/submissions.html.twig', [
            'mission' => $mission,
            'rendus' => $rendus,
        ]);
    }

    #[Route('/submission/{id}/review', name: 'app_admin_submission_review', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function reviewSubmission(int $id, Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rendu = $this->renduMissionRepository->find($id);
        if (!$rendu) {
            $this->addFlash('error', 'Soumission non trouvée.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $mission = $rendu->getMission();
        if (!$mission || $mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cette soumission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $action = is_string($request->request->get('action')) ? $request->request->get('action') : '';
        $feedback = $request->request->get('feedback', '');
        $feedbackString = is_string($feedback) ? $feedback : '';

        if ($action === 'accept') {
            $rendu->setStatut('accepte');
            $rendu->setFeedback($feedbackString ?: 'Félicitations ! Votre solution a été acceptée.');
            $this->addFlash('success', 'La soumission a été acceptée avec succès.');
        } elseif ($action === 'reject') {
            $rendu->setStatut('refuse');
            $rendu->setFeedback($feedbackString ?: 'Désolé, votre solution n\'a pas été retenue.');
            $this->addFlash('success', 'La soumission a été refusée.');
        } else {
            $this->addFlash('error', 'Action invalide.');
            return $this->redirectToRoute('app_admin_mission_submissions', ['id' => $mission->getId()]);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_admin_mission_submissions', ['id' => $mission->getId()]);
    }

    #[Route('/submission/{id}/view', name: 'app_admin_submission_view')]
    #[IsGranted('ROLE_RECRUITER')]
    public function viewSubmission(int $id): Response
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rendu = $this->renduMissionRepository->find($id);
        if (!$rendu) {
            $this->addFlash('error', 'Soumission non trouvée.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        $mission = $rendu->getMission();
        if (!$mission || $mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à voir cette soumission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        return $this->render('BackOffice/dashboard/missions/submission_view.html.twig', [
            'rendu' => $rendu,
            'mission' => $mission,
        ]);
    }

    /**
     * @return array{
     *     total_missions:int,
     *     total_submissions:int,
     *     missions_with_submissions:int,
     *     submission_rate:int,
     *     total_accepted:int,
     *     total_rejected:int,
     *     total_pending:int,
     *     acceptance_rate:int,
     *     average_score:float,
     *     max_score:float,
     *     min_score:float,
     *     score_distribution:array<string,int>
     * }
     */
    private function getMissionStats(User $user): array
    {
        $missions = $this->missionRepository->findByUserWithSearchAndSort($user, '', 'id', 'DESC');

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
            'submission_rate' => $totalMissions > 0 ? (int) round(($missionsWithSubmissions / $totalMissions) * 100) : 0,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'total_pending' => $totalPending,
            'acceptance_rate' => $totalSubmissions > 0 ? (int) round(($totalAccepted / $totalSubmissions) * 100) : 0,
            'average_score' => round($averageScore, 1),
            'max_score' => round($maxScore, 1),
            'min_score' => round($minScore, 1),
            'score_distribution' => $scoreDistribution,
        ];
    }
}
