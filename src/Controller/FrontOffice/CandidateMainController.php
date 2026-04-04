<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CandidateMainController extends AbstractController
{
    private const COURSES_PER_PAGE = 6;

    public function __construct(
        private CoursRepository $coursRepository,
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
    ) {
    }

    #[Route('', name: 'app_candidate_main')]
    public function main(): Response
    {
        return $this->render('FrontOffice/main/main.html.twig');
    }

    #[Route('/cours', name: 'app_candidate_cours')]
    public function cours(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $niveau = trim((string) $request->query->get('niveau', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $viewedLessonIds = $this->getViewedLessonIds($request);

        $total = $this->coursRepository->countForCandidateFilters($query, $niveau);
        $totalPages = max(1, (int) ceil($total / self::COURSES_PER_PAGE));
        $page = min($page, $totalPages);
        $courses = $this->coursRepository->searchForCandidate($query, $niveau, $page, self::COURSES_PER_PAGE);

        $courseStates = [];
        foreach ($courses as $course) {
            $courseStates[$course->getId()] = $this->buildCourseOutline($course, $viewedLessonIds);
        }

        return $this->render('FrontOffice/main/cours.html.twig', [
            'cours_list' => $courses,
            'niveaux' => $this->coursRepository->findDistinctNiveaux(),
            'filters' => [
                'q' => $query,
                'niveau' => $niveau,
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

    #[Route('/cours/{id}', name: 'app_candidate_cours_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function coursShow(Request $request, Cours $cours): Response
    {
        $outline = $this->buildCourseOutline($cours, $this->getViewedLessonIds($request));

        return $this->render('FrontOffice/main/cours_show.html.twig', [
            'cours' => $cours,
            'modules' => $outline['modules'],
            'lessons_by_module' => $outline['lessons_by_module'],
            'progress' => $outline['progress'],
            'continue_lesson' => $outline['continue_lesson'],
            'viewed_lesson_ids' => $outline['viewed_lesson_ids'],
        ]);
    }

    #[Route('/cours/{cours}/lecons/{id}', name: 'app_candidate_lecon_show', methods: ['GET'], requirements: ['cours' => '\\d+', 'id' => '\\d+'])]
    public function leconShow(Request $request, Cours $cours, Lecon $lecon): Response
    {
        if ($lecon->getModule()?->getCours()?->getId() !== $cours->getId()) {
            throw $this->createNotFoundException();
        }

        $this->markLessonViewed($request, $lecon);
        $outline = $this->buildCourseOutline($cours, $this->getViewedLessonIds($request));

        return $this->render('FrontOffice/main/lecon_show.html.twig', [
            'cours' => $cours,
            'lecon' => $lecon,
            'modules' => $outline['modules'],
            'lessons_by_module' => $outline['lessons_by_module'],
            'progress' => $outline['progress'],
            'continue_lesson' => $outline['continue_lesson'],
            'viewed_lesson_ids' => $outline['viewed_lesson_ids'],
        ]);
    }

    #[Route('/media/cours/{id}/image', name: 'app_candidate_cours_image', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function coursImage(Cours $cours): Response
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

    #[Route('/cours/{cours}/lecons/{id}/video', name: 'app_candidate_lecon_video', methods: ['GET'], requirements: ['cours' => '\\d+', 'id' => '\\d+'])]
    public function leconVideo(Cours $cours, Lecon $lecon): Response
    {
        if ($lecon->getModule()?->getCours()?->getId() !== $cours->getId()) {
            throw $this->createNotFoundException();
        }

        $content = $this->readBlobContent($lecon->getVideo());
        if ($content === null || $content === '') {
            throw $this->createNotFoundException();
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $this->detectMimeType($content, 'video/mp4'),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    #[Route('/offres', name: 'app_candidate_offres')]
    public function offres(): Response
    {
        return $this->render('FrontOffice/main/offres.html.twig');
    }

    #[Route('/mission', name: 'app_candidate_mission')]
    public function mission(): Response
    {
        return $this->render('FrontOffice/main/mission.html.twig');
    }

    #[Route('/reclamation', name: 'app_candidate_reclamation')]
    public function reclamation(): Response
    {
        return $this->render('FrontOffice/main/reclamation.html.twig');
    }

    #[Route('/messagerie', name: 'app_candidate_messagerie')]
    public function messagerie(): Response
    {
        return $this->render('FrontOffice/main/messagerie.html.twig');
    }

    /**
     * @return int[]
     */
    private function getViewedLessonIds(Request $request): array
    {
        $ids = $request->getSession()->get('viewed_candidate_lessons', []);

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }

    private function markLessonViewed(Request $request, Lecon $lecon): void
    {
        $ids = $this->getViewedLessonIds($request);
        if (!\in_array($lecon->getId(), $ids, true)) {
            $ids[] = $lecon->getId();
            $request->getSession()->set('viewed_candidate_lessons', $ids);
        }
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
            $moduleId = $lesson->getModuleId();
            if ($moduleId === null) {
                continue;
            }

            $lessonsByModule[$moduleId][] = $lesson;
            $totalLessons++;

            if (isset($viewedLookup[$lesson->getId()])) {
                $viewedCount++;
                continue;
            }

            if ($continueLesson === null) {
                $continueLesson = $lesson;
            }
        }

        return [
            'modules' => $modules,
            'lessons_by_module' => $lessonsByModule,
            'viewed_lesson_ids' => $viewedLookup,
            'progress' => $totalLessons > 0 ? (int) round(($viewedCount / $totalLessons) * 100) : 0,
            'continue_lesson' => $continueLesson,
        ];
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

        if (strlen($binary) >= 8 && substr($binary, 4, 4) === 'ftyp') {
            return 'video/mp4';
        }

        return $default;
    }

}
