<?php


declare(strict_types=1);
namespace App\Controller\DashboardController;



use App\Service\StatistiqueService;
use App\Service\ReclamationService;
use App\Service\FeedbackService;
use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/stats')]
class StatsController extends AbstractController
{
    use UserTypeCasterTrait;

private function checkRecruiter(): void
{
    $user = $this->getAuthenticatedUser();
    if (!$user || $user->getType() !== 'RECRUITER') {
        throw $this->createAccessDeniedException('Accès réservé aux recruteurs');
    }
}
    #[Route('/', name: 'app_dashboard_stats')]
    public function index(
        StatistiqueService $statsService,
        ReclamationService $reclamationService
    ): Response {
        $user = $this->getAuthenticatedUser();
        
        $stats = $statsService->getDashboardStats();
        $urgentReclamations = $reclamationService->getUrgentReclamations();
        
        return $this->render('BackOffice/dashboard/stats/index.html.twig', [
            'stats' => $stats,
            'urgent_reclamations' => $urgentReclamations,
        ]);
    }

    #[Route('/export/reclamations', name: 'app_dashboard_export_reclamations')]
public function exportReclamations(ExportService $exportService): BinaryFileResponse
{
    $this->checkRecruiter();
    
    $file = $exportService->exportReclamationsToCsv();
    
    return $this->file($file, 'reclamations_' . date('Y-m-d') . '.csv');
}
}
