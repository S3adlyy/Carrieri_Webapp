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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin/missions')]
class MissionController extends AbstractController
{
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les paramètres de recherche et de tri
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = $request->query->get('sort_order', 'DESC');

        // Valider les paramètres de tri
        $allowedSortFields = ['id', 'description', 'type', 'scoreMin', 'createdAt'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Récupérer les missions avec recherche et tri
        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $search,
            $sortBy,
            $sortOrder
        );

        return $this->render('BackOffice/dashboard/missions/index.html.twig', [
            'missions' => $missions,
            'is_admin_view' => false,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ]);
    }

    #[Route('/export/excel', name: 'app_admin_missions_export_excel')]
    #[IsGranted('ROLE_RECRUITER')]
    public function exportExcel(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les filtres pour l'export
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = $request->query->get('sort_order', 'DESC');

        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $search,
            $sortBy,
            $sortOrder
        );

        // Générer le HTML pour l'export Excel
        $html = $this->renderView('BackOffice/dashboard/missions/export_excel.html.twig', [
            'missions' => $missions,
            'export_date' => date('d/m/Y H:i:s'),
            'search' => $search,
        ]);

        // Headers pour forcer le téléchargement en tant que fichier Excel
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer les filtres pour l'export
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortOrder = $request->query->get('sort_order', 'DESC');

        $missions = $this->missionRepository->findByUserWithSearchAndSort(
            $user,
            $search,
            $sortBy,
            $sortOrder
        );

        // Générer le HTML pour le PDF
        $html = $this->renderView('BackOffice/dashboard/missions/export_pdf.html.twig', [
            'missions' => $missions,
            'export_date' => date('d/m/Y H:i:s'),
            'search' => $search,
        ]);

        // Configurer Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Générer le PDF
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = new Mission();
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mission->setUser($user);
            $mission->setCreatedAt(new \DateTime());
            $mission->setCreatedById($user->getId());

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
        $user = $this->getUser();
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($mission->getUser() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
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
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($mission->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette mission.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        if ($this->isCsrfTokenValid('delete' . $mission->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($mission);
            $this->entityManager->flush();
            $this->addFlash('success', 'La mission a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_missions_list');
    }

// src/Controller/DashboardController/MissionController.php

// Ajoutez cette méthode à votre contrôleur existant

    #[Route('/{id}/submissions', name: 'app_admin_mission_submissions')]
    #[IsGranted('ROLE_RECRUITER')]
    public function submissions(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = $this->missionRepository->find($id);
        if (!$mission || $mission->getUser() !== $user) {
            $this->addFlash('error', 'Mission non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('app_admin_missions_list');
        }

        // Récupérer tous les rendus pour cette mission
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
        $user = $this->getUser();
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

        $action = $request->request->get('action');
        $feedback = $request->request->get('feedback', '');

        if ($action === 'accept') {
            $rendu->setStatut('accepte');
            $rendu->setFeedback($feedback ?: 'Félicitations ! Votre solution a été acceptée.');
            $this->addFlash('success', 'La soumission a été acceptée avec succès.');
        } elseif ($action === 'reject') {
            $rendu->setStatut('refuse');
            $rendu->setFeedback($feedback ?: 'Désolé, votre solution n\'a pas été retenue.');
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
        $user = $this->getUser();
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

    #[Route('/status/{id}', name: 'app_candidate_rendu_status')]
    #[IsGranted('ROLE_CANDIDAT')]
    public function status(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rendu = $this->renduMissionRepository->find($id);
        if (!$rendu || $rendu->getUser() !== $user) {
            throw $this->createNotFoundException('Soumission non trouvée');
        }

        $mission = $rendu->getMission();

        return $this->render('FrontOffice/main/rendu_status.html.twig', [
            'rendu' => $rendu,
            'mission' => $mission,
        ]);
    }


}