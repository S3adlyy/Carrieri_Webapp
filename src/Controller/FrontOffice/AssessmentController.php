<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Entity\Module;
use App\Entity\User;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\ResultatQuizModuleRepository;
use App\Repository\ResultatTestCoursRepository;
use App\Service\CandidateAssessmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class AssessmentController extends AbstractController
{
    public function __construct(
        private CandidateAssessmentService $assessmentService,
        private LeconRepository $leconRepository,
        private ModuleRepository $moduleRepository,
        private ResultatQuizModuleRepository $resultatQuizModuleRepository,
        private ResultatTestCoursRepository $resultatTestCoursRepository,
    ) {
    }

    #[Route('/modules/{id}/quiz', name: 'app_candidate_module_quiz', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function moduleQuiz(Request $request, Module $module): Response
    {
        $user = $this->getAuthenticatedCandidate();
        $moduleGate = $this->computeModuleQuizGate($request, $module);
        $payload = ['questions' => [], 'expected_count' => 5];
        if ($moduleGate['unlocked']) {
            $payload = $this->assessmentService->buildModuleQuiz($module);
        }
        $result = null;

        if (!$moduleGate['unlocked']) {
            if ($request->isMethod('POST')) {
                $this->addFlash('warning', 'Vous devez lire toutes les lecons de ce module avant de passer le quiz.');
            }
        } elseif ($request->isMethod('POST') && $payload['questions'] !== []) {
            $submittedAnswers = (array) $request->request->all('answers');
            $result = $this->assessmentService->evaluate($payload['questions'], $submittedAnswers);

            if ($result['missing_question_ids'] !== []) {
                $this->addFlash('warning', 'Veuillez répondre à toutes les questions avant de terminer.');
            } else {
                $this->assessmentService->saveModuleResult($user, $module, $result);
                $this->addFlash(
                    $result['passed'] ? 'success' : 'warning',
                    sprintf('Quiz terminé : %d/%d (%s%%).', $result['score'], $result['total_points'], (string) $result['percentage'])
                );

                if ($module->getCours()?->getId() !== null) {
                    return $this->redirectToRoute('app_candidate_cours_show', ['id' => $module->getCours()->getId()]);
                }
            }
        }

        $latest = $this->resultatQuizModuleRepository->findLatestForCandidateAndModule((int) $user->getId(), (int) $module->getId());

        return $this->render('FrontOffice/main/module_quiz.html.twig', [
            'module' => $module,
            'cours' => $module->getCours(),
            'questions' => $payload['questions'],
            'expected_count' => $payload['expected_count'],
            'pass_threshold' => 70,
            'result' => $result,
            'latest_result' => $latest,
            'module_quiz_unlocked' => $moduleGate['unlocked'],
            'module_lessons_total' => $moduleGate['total_lessons'],
            'module_lessons_viewed' => $moduleGate['viewed_lessons'],
        ]);
    }

    #[Route('/cours/{id}/test-final', name: 'app_candidate_cours_test_final', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function coursFinalTest(Request $request, Cours $cours): Response
    {
        $user = $this->getAuthenticatedCandidate();
        $gate = $this->computeFinalTestGate($user, $cours);
        $payload = ['questions' => [], 'expected_count' => 15];
        if ($gate['unlocked']) {
            $payload = $this->assessmentService->buildCoursFinalTest($cours);
        }
        $result = null;

        if (!$gate['unlocked']) {
            if ($request->isMethod('POST')) {
                $this->addFlash('warning', 'Le test final est bloque tant que tous les quiz modules ne sont pas reussis.');
            }
        } elseif ($request->isMethod('POST') && $payload['questions'] !== []) {
            $submittedAnswers = (array) $request->request->all('answers');
            $result = $this->assessmentService->evaluate($payload['questions'], $submittedAnswers);

            if ($result['missing_question_ids'] !== []) {
                $this->addFlash('warning', 'Veuillez répondre à toutes les questions avant de terminer.');
            } else {
                $this->assessmentService->saveCoursResult($user, $cours, $result);
                $this->addFlash(
                    $result['passed'] ? 'success' : 'warning',
                    sprintf('Test final terminé : %d/%d (%s%%).', $result['score'], $result['total_points'], (string) $result['percentage'])
                );

                return $this->redirectToRoute('app_candidate_cours_show', ['id' => $cours->getId()]);
            }
        }

        $latest = $this->resultatTestCoursRepository->findLatestForCandidateAndCours((int) $user->getId(), (int) $cours->getId());

        return $this->render('FrontOffice/main/cours_test_final.html.twig', [
            'cours' => $cours,
            'questions' => $payload['questions'],
            'expected_count' => $payload['expected_count'],
            'pass_threshold' => 70,
            'result' => $result,
            'latest_result' => $latest,
            'final_test_unlocked' => $gate['unlocked'],
            'quiz_total_count' => $gate['total'],
            'quiz_passed_count' => $gate['passed'],
        ]);
    }

    /**
     * @return array{unlocked: bool, total: int, passed: int}
     */
    private function computeFinalTestGate(User $user, Cours $cours): array
    {
        $modules = $this->moduleRepository->findByCours($cours);
        if ($modules === []) {
            return ['unlocked' => false, 'total' => 0, 'passed' => 0];
        }

        $passed = 0;
        foreach ($modules as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $latest = $this->resultatQuizModuleRepository->findLatestForCandidateAndModule((int) $user->getId(), $moduleId);
            if ($latest !== null && (int) $latest->getReussite() === 1) {
                $passed++;
            }
        }

        $total = count($modules);

        return [
            'unlocked' => $total > 0 && $passed >= $total,
            'total' => $total,
            'passed' => $passed,
        ];
    }

    private function getAuthenticatedCandidate(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array{unlocked: bool, total_lessons: int, viewed_lessons: int}
     */
    private function computeModuleQuizGate(Request $request, Module $module): array
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            return ['unlocked' => false, 'total_lessons' => 0, 'viewed_lessons' => 0];
        }

        $lessons = $this->leconRepository->findBy(['moduleId' => $moduleId], ['ordre' => 'ASC', 'id' => 'ASC']);
        $total = count($lessons);
        if ($total === 0) {
            return ['unlocked' => false, 'total_lessons' => 0, 'viewed_lessons' => 0];
        }

        $viewedIds = array_values(array_unique(array_map('intval', (array) $request->getSession()->get('viewed_candidate_lessons', []))));
        $viewedLookup = array_fill_keys($viewedIds, true);
        $viewed = 0;

        foreach ($lessons as $lesson) {
            $lessonId = $lesson->getId();
            if ($lessonId !== null && isset($viewedLookup[$lessonId])) {
                $viewed++;
            }
        }

        return [
            'unlocked' => $viewed >= $total,
            'total_lessons' => $total,
            'viewed_lessons' => $viewed,
        ];
    }
}

