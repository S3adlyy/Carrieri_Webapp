<?php

namespace App\Controller\DashboardController;

use App\Service\ExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Service\FeedbackService;
use App\Entity\Feedback;
use App\Entity\RenduMission;
use App\Form\FeedbackType;
use App\Repository\FeedbackRepository;
use App\Repository\RenduMissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/feedback')]
class FeedbackController extends AbstractController
{
    private function checkRecruiter(): void
    {
        $user = $this->getUser();
        if (!$user || $user->getType() !== 'RECRUITER') {
            throw $this->createAccessDeniedException('Accès réservé aux recruteurs');
        }
    }
    
    #[Route('/rendus', name: 'app_dashboard_feedback_rendus', methods: ['GET'])]
    public function rendus(RenduMissionRepository $renduRepository): Response
    {
        $this->checkRecruiter();
        
        $rendus = $renduRepository->findAll();
        
        return $this->render('BackOffice/dashboard/feedback/rendus.html.twig', [
            'rendus' => $rendus,
        ]);
    }
    
    #[Route('/new/{renduId}', name: 'app_dashboard_feedback_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, int $renduId, RenduMissionRepository $renduRepository): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        $rendu = $renduRepository->find($renduId);
        
        if (!$rendu) {
            throw $this->createNotFoundException('Rendu de mission non trouvé');
        }
        
        // Vérifier si un feedback existe déjà
        $existingFeedback = $em->getRepository(Feedback::class)->findOneBy(['renduMission' => $rendu]);
        
        if ($existingFeedback) {
            $this->addFlash('danger', 'Un feedback a déjà été donné pour ce rendu');
            return $this->redirectToRoute('app_dashboard_feedback_rendus');
        }
        
        $feedback = new Feedback();
        $form = $this->createForm(FeedbackType::class, $feedback);
        
        $feedback->setCreatedAt(new \DateTimeImmutable());
        $feedback->setUser($user);
        $feedback->setUtilisateurId($user->getId());
        $feedback->setRenduMission($rendu);
        $feedback->setRenduId($rendu->getId());
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($feedback);
            $em->flush();
            
            $this->addFlash('success', 'Votre feedback a été envoyé pour le rendu n°' . $rendu->getId());
            return $this->redirectToRoute('app_dashboard_feedback_rendus');
        }
        
        return $this->render('BackOffice/dashboard/feedback/new.html.twig', [
            'form' => $form->createView(),
            'rendu' => $rendu,
        ]);
    }
    
 #[Route('/', name: 'app_dashboard_feedback_index', methods: ['GET'])]
public function index(FeedbackRepository $repository, FeedbackService $feedbackService): Response
{
    $this->checkRecruiter();
    
    $user = $this->getUser();
    $feedbacks = $repository->findBy(['user' => $user], ['createdAt' => 'DESC']);
    $stats = $feedbackService->getStats($user);
    
    return $this->render('BackOffice/dashboard/feedback/index.html.twig', [
        'feedbacks' => $feedbacks,
        'stats' => $stats,
    ]);
}
#[Route('/export/excel', name: 'app_dashboard_export_feedbacks_excel', methods: ['GET'])]
public function exportExcel(ExportService $exportService): BinaryFileResponse
{
    $this->checkRecruiter();
    
    $file = $exportService->exportFeedbacksToExcel();
    
    return $this->file($file, 'feedbacks_' . date('Y-m-d') . '.xls');
}
#[Route('/export', name: 'app_dashboard_export_feedbacks', methods: ['GET'])]
public function export(ExportService $exportService): BinaryFileResponse
{
    $this->checkRecruiter();
    
    $file = $exportService->exportFeedbacksToCsv();
    
    return $this->file($file, 'feedbacks_' . date('Y-m-d') . '.csv');
}
    
    #[Route('/{id}', name: 'app_dashboard_feedback_show', methods: ['GET'])]
    public function show(Feedback $feedback): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        
        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès non autorisé');
        }
        
        return $this->render('BackOffice/dashboard/feedback/show.html.twig', [
            'feedback' => $feedback,
        ]);
    }
    
    #[Route('/{id}/edit', name: 'app_dashboard_feedback_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        
        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce feedback');
        }
        
        $form = $this->createForm(FeedbackType::class, $feedback);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre feedback a été modifié');
            return $this->redirectToRoute('app_dashboard_feedback_index');
        }
        
        return $this->render('BackOffice/dashboard/feedback/edit.html.twig', [
            'form' => $form->createView(),
            'feedback' => $feedback,
        ]);
    }
    
    #[Route('/{id}', name: 'app_dashboard_feedback_delete', methods: ['POST'])]
    public function delete(Request $request, Feedback $feedback, EntityManagerInterface $em): Response
    {
        $this->checkRecruiter();
        
        $user = $this->getUser();
        
        if ($feedback->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce feedback');
        }
        
        if ($this->isCsrfTokenValid('delete' . $feedback->getId(), $request->request->get('_token'))) {
            $em->remove($feedback);
            $em->flush();
            $this->addFlash('success', 'Votre feedback a été supprimé');
        }
        
        return $this->redirectToRoute('app_dashboard_feedback_index');
    }
}