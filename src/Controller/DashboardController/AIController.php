<?php

declare(strict_types=1);

namespace App\Controller\DashboardController;

use App\Controller\UserTypeCasterTrait;
use App\Entity\User;
use App\Service\AI\UrgencyDetectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/ai')]
class AIController extends AbstractController
{
    use UserTypeCasterTrait;

    private function checkRecruiter(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User || $user->getType() !== 'RECRUITER') {
            throw $this->createAccessDeniedException('Acces reserve aux recruteurs');
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
            $message = $result['message'] ?? 'Entrainement termine';
            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('app_dashboard_traitement_reclamations');
    }
}
