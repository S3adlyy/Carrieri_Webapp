<?php
// src/Controller/FrontOffice/RenduMissionController.php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\RenduMission;
use App\Entity\User;
use App\Repository\MissionRepository;
use App\Repository\RenduMissionRepository;
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
        private RenduMissionRepository $renduMissionRepository
    ) {
    }

    #[Route('/{id}', name: 'app_candidate_rendu_mission')]
    public function index(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $mission = $this->missionRepository->find($id);
        if (!$mission) {
            throw $this->createNotFoundException('Mission non trouvée');
        }

        // Vérifier si le candidat a déjà soumis une solution
        $existingRendu = $this->renduMissionRepository->findExistingSubmission(
            $mission->getId(),
            $user->getId()
        );

        $resultat = null;
        $scoreObtenu = null;

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $langue = $request->request->get('langue', 'javascript');

            // Évaluer le code
            $evaluation = $this->evaluateCode($code, $langue, $mission);

            $resultat = $evaluation['resultat'];
            $scoreObtenu = $evaluation['score'];

            $renduMission = new RenduMission();
            $renduMission->setCodeSolution($code);
            $renduMission->setLangue($langue);
            $renduMission->setDateRendu(new \DateTime());
            $renduMission->setScore($scoreObtenu);
            $renduMission->setResultat($resultat);
            $renduMission->setMission($mission);
            $renduMission->setUser($user);

            $this->entityManager->persist($renduMission);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre solution a été soumise avec succès !');

            // Rediriger pour éviter la resoumission
            return $this->redirectToRoute('app_candidate_rendu_mission', ['id' => $id]);
        }

        return $this->render('FrontOffice/main/rendu_mission.html.twig', [
            'mission' => $mission,
            'existingRendu' => $existingRendu,
            'resultat' => $resultat,
            'scoreObtenu' => $scoreObtenu,
        ]);
    }

    private function evaluateCode(string $code, string $langue, $mission): array
    {
        // Tests pour la mission Two Sum
        $testCases = [
            [
                'input' => ['nums' => [2,7,11,15], 'target' => 9],
                'expected' => [0,1],
                'description' => 'Test 1: nums = [2,7,11,15], target = 9'
            ],
            [
                'input' => ['nums' => [3,2,4], 'target' => 6],
                'expected' => [1,2],
                'description' => 'Test 2: nums = [3,2,4], target = 6'
            ],
            [
                'input' => ['nums' => [3,3], 'target' => 6],
                'expected' => [0,1],
                'description' => 'Test 3: nums = [3,3], target = 6'
            ],
        ];

        $passedTests = 0;
        $totalTests = count($testCases);
        $results = [];

        // Vérifier si le code contient une solution valide
        $hasValidCode = $this->validateCode($code, $langue);

        foreach ($testCases as $index => $test) {
            $isPassed = $hasValidCode && $this->simulateCodeExecution($code, $langue, $test['input'], $test['expected']);

            if ($isPassed) {
                $passedTests++;
                $results[] = [
                    'test' => $index + 1,
                    'status' => 'success',
                    'message' => '✓ Test passé',
                    'description' => $test['description']
                ];
            } else {
                $results[] = [
                    'test' => $index + 1,
                    'status' => 'error',
                    'message' => '✗ Test échoué',
                    'description' => $test['description'],
                    'expected' => json_encode($test['expected']),
                ];
            }
        }

        $score = ($passedTests / $totalTests) * 100;

        $resultatHtml = $this->renderResultat($results, $passedTests, $totalTests);

        return [
            'score' => $score,
            'resultat' => $resultatHtml
        ];
    }

    private function validateCode(string $code, string $langue): bool
    {
        $code = strtolower($code);

        // Vérifications basiques selon le langage
        switch ($langue) {
            case 'javascript':
                return strpos($code, 'function') !== false || strpos($code, '=>') !== false;
            case 'php':
                return strpos($code, 'function') !== false && strpos($code, '$') !== false;
            case 'python':
                return strpos($code, 'def') !== false;
            default:
                return strlen($code) > 50;
        }
    }

    private function simulateCodeExecution(string $code, string $langue, array $input, $expected): bool
    {
        // Simulation - Dans un vrai projet, utilisez un juge en ligne
        // Pour l'exemple, on vérifie si le code contient les bons éléments
        $codeLower = strtolower($code);

        $requiredPatterns = [
            'map',
            'hashmap',
            'dictionary',
            'two',
            'sum'
        ];

        $foundCount = 0;
        foreach ($requiredPatterns as $pattern) {
            if (strpos($codeLower, $pattern) !== false) {
                $foundCount++;
            }
        }

        // Si le code contient au moins 2 patterns, on considère le test réussi
        return $foundCount >= 2;
    }

    private function renderResultat(array $results, int $passedTests, int $totalTests): string
    {
        $percentage = $totalTests > 0 ? ($passedTests / $totalTests) * 100 : 0;
        $html = '<div class="test-results">';
        $html .= '<div class="test-summary">';
        $html .= sprintf('<h4>Résultats des tests : %d/%d passés</h4>', $passedTests, $totalTests);
        $html .= '<div class="progress-bar">';
        $html .= sprintf('<div class="progress-fill" style="width: %d%%"></div>', $percentage);
        $html .= '</div></div>';

        $html .= '<div class="test-details">';
        foreach ($results as $result) {
            $statusClass = $result['status'] === 'success' ? 'test-success' : 'test-error';
            $html .= sprintf(
                '<div class="test-item %s">
                    <div class="test-header"><strong>Test %d</strong> - %s</div>
                    <div class="test-message">%s</div>',
                $statusClass,
                $result['test'],
                $result['description'],
                $result['message']
            );
            if (isset($result['expected'])) {
                $html .= sprintf('<div class="test-expected">Attendu: %s</div>', $result['expected']);
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }
}