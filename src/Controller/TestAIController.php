<?php


declare(strict_types=1);
namespace App\Controller;

use App\Entity\Reclamation;
use App\Service\AI\UrgencyDetectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestAIController extends AbstractController
{
    #[Route('/test/ai', name: 'test_ai')]
    public function test(UrgencyDetectionService $ai): Response
    {
        // Créer une réclamation fictive pour tester
        $reclamation = new Reclamation();
        $reclamation->setObjet('Problème urgent !');
        $reclamation->setDescription('Je suis bloqué, besoin d\'aide immédiatement');
        
        $result = $ai->detectUrgency($reclamation);
        
        return $this->json([
            'objet' => $reclamation->getObjet(),
            'description' => $reclamation->getDescription(),
            'resultat_ia' => $result
        ]);
    }
}
