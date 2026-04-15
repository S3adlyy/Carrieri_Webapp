<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cours;
use App\Entity\User;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\ResultatQuizModuleRepository;
use App\Repository\ResultatTestCoursRepository;

final class CandidateRecommendationService
{
    public function __construct(
        private CoursRepository $coursRepository,
        private ModuleRepository $moduleRepository,
        private LeconRepository $leconRepository,
        private ResultatQuizModuleRepository $resultatQuizModuleRepository,
        private ResultatTestCoursRepository $resultatTestCoursRepository,
    ) {
    }

    /**
     * @param int[] $viewedLessonIds
     * @return array{
     *   inferred_level: string,
     *   followed_courses: list<Cours>,
     *   recommendations: list<array{course:Cours,score:int,reasons:list<string>}>,
     *   stats: array{followed:int,completed:int,total:int,explored_percent:int,available:int},
     *   levels: string[],
     *   selected_level: string
     * }
     */
    public function buildForCandidate(User $candidate, array $viewedLessonIds, string $selectedLevel): array
    {
        $candidateId = (int) $candidate->getId();
        $viewedLookup = array_fill_keys(array_map('intval', $viewedLessonIds), true);
        $courses = $this->coursRepository->findBy([], ['id' => 'DESC']);

        $progressByCourseId = [];
        $followedCourses = [];

        foreach ($courses as $course) {
            $courseId = $course->getId();
            if ($courseId === null) {
                continue;
            }

            $progress = $this->computeCompositeProgress($candidateId, $course, $viewedLookup);
            $progressByCourseId[$courseId] = $progress;

            if ($progress > 0) {
                $followedCourses[] = $course;
            }
        }

        $completedCourseIds = $this->resultatTestCoursRepository->findPassedCoursIdsForCandidate($candidateId);
        $completedLookup = array_fill_keys($completedCourseIds, true);
        $followedIds = array_map(static fn (Cours $course): int => (int) $course->getId(), $followedCourses);
        $followedLookup = array_fill_keys($followedIds, true);

        $coursesForProfile = $completedCourseIds !== [] ? $completedCourseIds : $followedIds;
        $inferredLevel = $this->inferLevelFromCourses($coursesForProfile, $courses);
        $skillProfile = $this->extractSkillProfile($coursesForProfile, $courses);

        $recommendationRows = [];
        foreach ($courses as $course) {
            $courseId = $course->getId();
            if ($courseId === null || isset($followedLookup[$courseId]) || isset($completedLookup[$courseId])) {
                continue;
            }

            ['score' => $score, 'reasons' => $reasons] = $this->computeRecommendationScore($course, $skillProfile, $inferredLevel);
            if ($score <= 0 && $skillProfile !== []) {
                continue;
            }

            $recommendationRows[] = [
                'course' => $course,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($recommendationRows, static function (array $left, array $right): int {
            if ($left['score'] === $right['score']) {
                return ((int) $right['course']->getId()) <=> ((int) $left['course']->getId());
            }

            return $right['score'] <=> $left['score'];
        });

        $selectedLevel = trim($selectedLevel);
        if ($selectedLevel !== '' && mb_strtolower($selectedLevel) !== 'tous') {
            $recommendationRows = array_values(array_filter(
                $recommendationRows,
                fn (array $row): bool => $this->normalizeLevel((string) $row['course']->getNiveau()) === $this->normalizeLevel($selectedLevel)
            ));
        }

        if ($skillProfile === []) {
            $recommendationRows = array_values(array_filter(
                $recommendationRows,
                fn (array $row): bool => $this->normalizeLevel((string) $row['course']->getNiveau()) === $this->normalizeLevel($inferredLevel)
            ));
        }

        $recommendationRows = array_slice($recommendationRows, 0, 8);

        $totalCourses = count($courses);
        $followedCount = count($followedCourses);

        return [
            'inferred_level' => $inferredLevel,
            'followed_courses' => $followedCourses,
            'recommendations' => $recommendationRows,
            'stats' => [
                'followed' => $followedCount,
                'completed' => count($completedCourseIds),
                'total' => $totalCourses,
                'explored_percent' => $totalCourses > 0 ? (int) round(($followedCount / $totalCourses) * 100) : 0,
                'available' => max(0, $totalCourses - $followedCount),
            ],
            'levels' => array_merge(['Tous'], $this->coursRepository->findDistinctNiveaux()),
            'selected_level' => $selectedLevel !== '' ? $selectedLevel : 'Tous',
        ];
    }

    /**
     * @param array<int, bool> $viewedLookup
     */
    private function computeCompositeProgress(int $candidateId, Cours $course, array $viewedLookup): int
    {
        $modules = $this->moduleRepository->findByCours($course);
        $lessons = $this->leconRepository->findByCours($course);

        $totalLessons = count($lessons);
        $viewedCount = 0;
        foreach ($lessons as $lesson) {
            $lessonId = $lesson->getId();
            if ($lessonId !== null && isset($viewedLookup[$lessonId])) {
                $viewedCount++;
            }
        }

        $moduleCount = count($modules);
        $completedModuleQuizzes = 0;

        foreach ($modules as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $quiz = $this->resultatQuizModuleRepository->findLatestForCandidateAndModule($candidateId, $moduleId);
            if ($quiz !== null && (int) $quiz->getReussite() === 1) {
                $completedModuleQuizzes++;
            }
        }

        $passedFinalTest = 0;
        $courseId = $course->getId();
        if ($courseId !== null) {
            $test = $this->resultatTestCoursRepository->findLatestForCandidateAndCours($candidateId, $courseId);
            $passedFinalTest = $test !== null && (int) $test->getReussite() === 1 ? 1 : 0;
        }

        if ($moduleCount === 0) {
            return $totalLessons > 0 ? (int) round(($viewedCount / $totalLessons) * 100) : 0;
        }

        $requiredItems = max(1, $totalLessons + $moduleCount + 1);
        $completedItems = $viewedCount + $completedModuleQuizzes + $passedFinalTest;

        return (int) round(($completedItems / $requiredItems) * 100);
    }

    /**
     * @param int[] $courseIds
     * @param list<Cours> $allCourses
     */
    private function inferLevelFromCourses(array $courseIds, array $allCourses): string
    {
        if ($courseIds === []) {
            return 'Débutant';
        }

        $lookup = array_fill_keys($courseIds, true);
        $weights = [
            'debutant' => 1,
            'intermediaire' => 2,
            'avance' => 3,
            'expert' => 4,
        ];

        $sum = 0;
        $count = 0;
        foreach ($allCourses as $course) {
            $courseId = $course->getId();
            if ($courseId === null || !isset($lookup[$courseId])) {
                continue;
            }

            $sum += $weights[$this->normalizeLevel((string) $course->getNiveau())] ?? 1;
            $count++;
        }

        if ($count === 0) {
            return 'Débutant';
        }

        $average = (int) round($sum / $count);
        return match (true) {
            $average <= 1 => 'Débutant',
            $average <= 2 => 'Intermédiaire',
            $average <= 3 => 'Avancé',
            default => 'Expert',
        };
    }

    /**
     * @param int[] $courseIds
     * @param list<Cours> $allCourses
     * @return array<string, true>
     */
    private function extractSkillProfile(array $courseIds, array $allCourses): array
    {
        $lookup = array_fill_keys($courseIds, true);
        $skills = [];

        foreach ($allCourses as $course) {
            $courseId = $course->getId();
            if ($courseId === null || !isset($lookup[$courseId])) {
                continue;
            }

            $skillsList = (string) ($course->getCompetencesVisees() ?? '');
            if ($skillsList === '') {
                continue;
            }

            foreach (explode(',', mb_strtolower($skillsList)) as $skill) {
                $skill = trim($skill);
                if ($skill !== '') {
                    $skills[$skill] = true;
                }
            }
        }

        return $skills;
    }

    /**
     * @param array<string, true> $skillProfile
     * @return array{score:int,reasons:list<string>}
     */
    private function computeRecommendationScore(Cours $course, array $skillProfile, string $inferredLevel): array
    {
        $score = 0;
        $reasons = [];
        $exactMatches = 0;
        $partialMatches = 0;
        $skillsList = (string) ($course->getCompetencesVisees() ?? '');

        if ($skillsList !== '' && $skillProfile !== []) {
            foreach (explode(',', mb_strtolower($skillsList)) as $courseSkillRaw) {
                $courseSkill = trim($courseSkillRaw);
                if ($courseSkill === '') {
                    continue;
                }

                foreach (array_keys($skillProfile) as $candidateSkill) {
                    if ($courseSkill === $candidateSkill) {
                        $score += 10;
                        $exactMatches++;
                        continue;
                    }

                    if (str_contains($courseSkill, $candidateSkill) || str_contains($candidateSkill, $courseSkill)) {
                        $score += 5;
                        $partialMatches++;
                    }
                }
            }
        }

        $courseLevel = $this->normalizeLevel((string) $course->getNiveau());
        $candidateLevel = $this->normalizeLevel($inferredLevel);

        if ($courseLevel === $candidateLevel) {
            $score += 3;
            $reasons[] = 'Niveau adapte a votre profil actuel';
        } elseif ($this->isNextLevel($candidateLevel, $courseLevel)) {
            $score += 2;
            $reasons[] = 'Bon prochain palier pour progresser';
        }

        if ($exactMatches > 0) {
            $reasons[] = sprintf('Correspond a %d competence(s) deja validee(s)', $exactMatches);
        }

        if ($partialMatches > 0 && $exactMatches === 0) {
            $reasons[] = 'Renforce des competences proches de votre parcours';
        }

        $duration = (int) ($course->getDuree() ?? 0);
        if ($duration > 0 && $duration <= 8) {
            $score += 1;
            $reasons[] = 'Format court pour monter rapidement en competence';
        }

        if ($skillsList === '' && $score > 0) {
            $reasons[] = 'Cours complementaire recommande dans votre parcours';
        }

        if ($score <= 0) {
            return ['score' => 0, 'reasons' => []];
        }

        if ($reasons === []) {
            $reasons[] = 'Recommande selon votre progression recente';
        }

        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function isNextLevel(string $currentLevel, string $targetLevel): bool
    {
        $weights = [
            'debutant' => 1,
            'intermediaire' => 2,
            'avance' => 3,
            'expert' => 4,
        ];

        $currentWeight = $weights[$currentLevel] ?? 1;
        $targetWeight = $weights[$targetLevel] ?? 1;

        return $targetWeight === ($currentWeight + 1);
    }

    private function normalizeLevel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $value);

        return match (true) {
            str_contains($value, 'debut') => 'debutant',
            str_contains($value, 'inter') => 'intermediaire',
            str_contains($value, 'avan') => 'avance',
            str_contains($value, 'expert') => 'expert',
            default => 'debutant',
        };
    }
}

