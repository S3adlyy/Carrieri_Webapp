<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class LeconController extends AbstractController
{
    public function __construct(
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
    ) {
    }

    #[Route('/cours/{cours}/lecons/{id}', name: 'app_candidate_lecon_show', methods: ['GET'], requirements: ['cours' => '\\d+', 'id' => '\\d+'])]
    public function show(Request $request, Cours $cours, Lecon $lecon): Response
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

    #[Route('/cours/{cours}/lecons/{id}/video', name: 'app_candidate_lecon_video', methods: ['GET'], requirements: ['cours' => '\\d+', 'id' => '\\d+'])]
    public function video(Cours $cours, Lecon $lecon): Response
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
        $lessonId = $lecon->getId();
        if ($lessonId === null) {
            return;
        }

        $ids = $this->getViewedLessonIds($request);
        if (!\in_array($lessonId, $ids, true)) {
            $ids[] = $lessonId;
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
        if (strlen($binary) >= 8 && substr($binary, 4, 4) === 'ftyp') {
            return 'video/mp4';
        }

        return $default;
    }
}