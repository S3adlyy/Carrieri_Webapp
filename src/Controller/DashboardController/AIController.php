<?php

namespace App\Controller\DashboardController;

use App\Service\AI\UrgencyDetectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/ai')]
class AIController extends AbstractController
{
    private function checkRecruiter(): void
    {
        $user = $this->getUser();
        if (!$user || $user->getType() !== 'RECRUITER') {
            throw $this->createAccessDeniedException('Accès réservé aux recruteurs');
        }
    }

    #[Route('/train', name: 'app_dashboard_ai_train', methods: ['GET'])]
    public function train(UrgencyDetectionService $ai): Response
    {
        $this->checkRecruiter();
        
        $result = $ai->trainWithRealData();
        
        if (isset($result['error'])) {
            $this->addFlash('danger', $result['error']);
        } else {
            $this->addFlash('success', $result['message']);
        }
        
        return $this->redirectToRoute('app_dashboard_traitement_reclamations');
    }
}