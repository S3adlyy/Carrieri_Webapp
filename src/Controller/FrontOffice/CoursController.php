<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\ResultatQuizModuleRepository;
use App\Repository\ResultatTestCoursRepository;
use App\Service\CandidateRecommendationService;
use App\Service\CertificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CoursController extends AbstractController
{
    private const COURSES_PER_PAGE = 6;

    public function __construct(
        private CoursRepository $coursRepository,
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
        private ResultatQuizModuleRepository $resultatQuizModuleRepository,
        private ResultatTestCoursRepository $resultatTestCoursRepository,
        private CertificationService $certificationService,
        private CandidateRecommendationService $candidateRecommendationService,
    ) {
    }

    #[Route('/cours', name: 'app_candidate_cours')]
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $niveau = trim((string) $request->query->get('niveau', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $viewedLessonIds = $this->getViewedLessonIds($request);
        $order = $request->query->get('order', 'recent');

        $total = $this->coursRepository->countForCandidateFilters($query, $niveau);
        $totalPages = max(1, (int) ceil($total / self::COURSES_PER_PAGE));
        $page = min($page, $totalPages);
        $courses = $this->coursRepository->searchForCandidate($query, $niveau, $order, $page, self::COURSES_PER_PAGE);

        $courseStates = [];
        foreach ($courses as $course) {
            $courseId = $course->getId();
            if ($courseId === null) {
                continue;
            }

            $state = $this->buildCourseOutline($course, $viewedLessonIds);
            $courseStates[$courseId] = $state;
        }

        return $this->render('FrontOffice/main/cours.html.twig', [
            'cours_list' => $courses,
            'niveaux' => $this->coursRepository->findDistinctNiveaux(),
            'filters' => [
                'q' => $query,
                'niveau' => $niveau,
                'order' => $order,

            ],

            'pagination' => [
                'page' => $page,
                'limit' => self::COURSES_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'course_states' => $courseStates,
        ]);
    }

    #[Route('/mes-cours', name: 'app_candidate_mes_cours')]
    public function myCourses(Request $request): Response
    {
        $myQuery = trim((string) $request->query->get('my_q', ''));
        $myState = strtolower(trim((string) $request->query->get('my_state', 'all')));
        if (!in_array($myState, ['all', 'in_progress', 'completed'], true)) {
            $myState = 'all';
        }

        $myData = $this->buildMyCoursesData($request, $myQuery, $myState);

        return $this->render('FrontOffice/main/mes_cours.html.twig', [
            'my_courses' => $myData['courses'],
            'my_course_states' => $myData['states'],
            'my_courses_stats' => $myData['stats'],
            'my_filters' => [
                'q' => $myQuery,
                'state' => $myState,
            ],
        ]);
    }

    #[Route('/mes-recommandations', name: 'app_candidate_mes_recommandations')]
    public function recommendations(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        $selectedLevel = trim((string) $request->query->get('niveau', 'Tous'));
        $data = $this->candidateRecommendationService->buildForCandidate(
            $user,
            $this->getViewedLessonIds($request),
            $selectedLevel
        );

        return $this->render('FrontOffice/main/mes_recommandations.html.twig', [
            'recommendation_data' => $data,
        ]);
    }

    #[Route('/cours/{id}', name: 'app_candidate_cours_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Request $request, Cours $cours): Response
    {
        $outline = $this->buildCourseOutline($cours, $this->getViewedLessonIds($request));
        $gate = $this->computeFinalTestGate($cours);

        // Créer un certificat si le cours est complété
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $progress = (int) ($outline['progress'] ?? 0);
            $this->certificationService->createCertificateIfCompleted($user, $cours, $progress);
        }

        return $this->render('FrontOffice/main/cours_show.html.twig', [
            'cours' => $cours,
            'modules' => $outline['modules'],
            'lessons_by_module' => $outline['lessons_by_module'],
            'progress' => $outline['progress'],
            'continue_lesson' => $outline['continue_lesson'],
            'viewed_lesson_ids' => $outline['viewed_lesson_ids'],
            'final_test_unlocked' => $gate['unlocked'],
            'quiz_total_count' => $gate['total'],
            'quiz_passed_count' => $gate['passed'],
        ]);
    }

    #[Route('/media/cours/{id}/image', name: 'app_candidate_cours_image', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function image(Cours $cours): Response
    {
        $content = $this->readBlobContent($cours->getImageCouverture());
        if ($content === null || $content === '') {
            throw $this->createNotFoundException();
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $this->detectMimeType($content, 'image/jpeg'),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return int[]
     */
    private function getViewedLessonIds(Request $request): array
    {
        $ids = $request->getSession()->get('viewed_candidate_lessons', []);

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }

    /**
     * @param int[] $viewedLessonIds
     * @return array{modules: array<int, \App\Entity\Module>, lessons_by_module: array<int, array<int, Lecon>>, viewed_lesson_ids: array<int, bool>, progress: int, continue_lesson: ?Lecon}
     */
    private function buildCourseOutline(Cours $cours, array $viewedLessonIds): array
    {
        $modules = $this->moduleRepository->findByCours($cours);
        $lessonsByModule = [];
        $viewedLookup = array_fill_keys($viewedLessonIds, true);
        $continueLesson = null;
        $totalLessons = 0;
        $viewedCount = 0;

        $lessons = $this->leconRepository->findByCours($cours);
        foreach ($lessons as $lesson) {
            $lessonId = $lesson->getId();
            $moduleId = $lesson->getModuleId();
            if ($lessonId === null || $moduleId === null) {
                continue;
            }

            $lessonsByModule[$moduleId][] = $lesson;
            $totalLessons++;

            if (isset($viewedLookup[$lessonId])) {
                $viewedCount++;
                continue;
            }

            if ($continueLesson === null) {
                $continueLesson = $lesson;
            }
        }

        $progress = $this->computeCompositeProgress($cours, $modules, $totalLessons, $viewedCount, count($viewedLookup));

        return [
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
            'viewed_lesson_ids' => $viewedLookup,
            'progress' => $progress,
            'continue_lesson' => $continueLesson,
        ];
    }

    /**
     * @param array<int, \App\Entity\Module> $modules
     */
    private function computeCompositeProgress(Cours $cours, array $modules, int $totalLessons, int $viewedCount, int $viewedLessonCount): int
    {
        $moduleCount = count($modules);
        $completedModuleQuizzes = 0;
        $passedFinalTest = 0;

        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && $user->getId() !== null) {
            foreach ($modules as $module) {
                $moduleId = $module->getId();
                if ($moduleId === null) {
                    continue;
                }

                $latestQuiz = $this->resultatQuizModuleRepository->findLatestForCandidateAndModule((int) $user->getId(), $moduleId);
                if ($latestQuiz !== null && (int) $latestQuiz->getReussite() === 1) {
                    $completedModuleQuizzes++;
                }
            }

            $coursId = $cours->getId();
            if ($coursId !== null) {
                $latestTest = $this->resultatTestCoursRepository->findLatestForCandidateAndCours((int) $user->getId(), $coursId);
                $passedFinalTest = $latestTest !== null && (int) $latestTest->getReussite() === 1 ? 1 : 0;
            } else {
                $passedFinalTest = 0;
            }
        } else {
            $passedFinalTest = 0;
        }

        if ($moduleCount === 0) {
            return $totalLessons > 0 ? (int) round(($viewedCount / $totalLessons) * 100) : 0;
        }

        $requiredItems = max(1, $totalLessons + $moduleCount + 1);
        $completedItems = $viewedCount + $completedModuleQuizzes + $passedFinalTest;

        return (int) round(($completedItems / $requiredItems) * 100);
    }

    private function readBlobContent(mixed $blob): ?string
    {
        if ($blob === null) {
            return null;
        }

        if (is_resource($blob)) {
            $meta = stream_get_meta_data($blob);
            if (($meta['seekable'] ?? false) === true) {
                rewind($blob);
            }

            $content = stream_get_contents($blob);

            return $content === false ? null : $content;
        }

        if (is_string($blob)) {
            return $blob;
        }

        return null;
    }

    private function detectMimeType(string $binary, string $default): string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
            return 'image/gif';
        }

        if (strlen($binary) >= 12 && substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return $default;
    }

    /**
     * @return array{courses: array<int, Cours>, states: array<int, array<string, mixed>>, stats: array{total: int, in_progress: int, completed: int, hours: int}}
     */
    private function buildMyCoursesData(Request $request, string $myQuery, string $myState): array
    {
        $viewedLessonIds = $this->getViewedLessonIds($request);
        $allCourses = $this->coursRepository->findBy([], ['id' => 'DESC']);
        $allCourseStates = [];
        $myStartedCourses = [];
        $myStats = [
            'total' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'hours' => 0,
        ];

        foreach ($allCourses as $course) {
            $courseId = $course->getId();
            if ($courseId === null) {
                continue;
            }

            $state = $this->buildCourseOutline($course, $viewedLessonIds);
            $allCourseStates[$courseId] = $state;

            $progress = (int) ($state['progress'] ?? 0);
            if ($progress <= 0) {
                continue;
            }

            $myStartedCourses[] = $course;
            $myStats['total']++;
            if ($progress >= 100) {
                $myStats['completed']++;
            } else {
                $myStats['in_progress']++;
            }

            $duration = (float) ($course->getDuree() ?? 0);
            $myStats['hours'] += (int) round(($duration * $progress) / 100);
        }

        $myCourses = array_values(array_filter($myStartedCourses, static function (Cours $course) use ($allCourseStates, $myState, $myQuery): bool {
            $courseId = $course->getId();
            if ($courseId === null) {
                return false;
            }

            $progress = (int) ($allCourseStates[$courseId]['progress'] ?? 0);
            if ($myState === 'in_progress' && $progress >= 100) {
                return false;
            }
            if ($myState === 'completed' && $progress < 100) {
                return false;
            }

            if ($myQuery === '') {
                return true;
            }

            $needle = mb_strtolower($myQuery);
            $haystack = mb_strtolower(trim((string) $course->getTitre() . ' ' . (string) $course->getDescription()));

            return str_contains($haystack, $needle);
        }));

        return [
            'courses' => $myCourses,
            'states' => $allCourseStates,
            'stats' => $myStats,
        ];
    }

    /**
     * @return array{unlocked: bool, total: int, passed: int}
     */
    private function computeFinalTestGate(Cours $cours): array
    {
        $modules = $this->moduleRepository->findByCours($cours);
        if ($modules === []) {
            return ['unlocked' => false, 'total' => 0, 'passed' => 0];
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User || $user->getId() === null) {
            return ['unlocked' => false, 'total' => count($modules), 'passed' => 0];
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
}