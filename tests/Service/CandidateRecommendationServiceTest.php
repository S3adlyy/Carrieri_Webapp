<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cours;
use App\Entity\User;
use App\Repository\CoursRepository;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\ResultatQuizModuleRepository;
use App\Repository\ResultatTestCoursRepository;
use App\Service\CandidateRecommendationService;
use PHPUnit\Framework\TestCase;

final class CandidateRecommendationServiceTest extends TestCase
{
    public function testBuildForCandidateReturnsScoredRecommendationsWithReasons(): void
    {
        $coursRepository = $this->getMockBuilder(CoursRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy', 'findDistinctNiveaux'])
            ->getMock();

        $moduleRepository = $this->getMockBuilder(ModuleRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByCours'])
            ->getMock();

        $leconRepository = $this->getMockBuilder(LeconRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByCours'])
            ->getMock();

        $quizRepository = $this->getMockBuilder(ResultatQuizModuleRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findLatestForCandidateAndModule'])
            ->getMock();

        $testRepository = $this->getMockBuilder(ResultatTestCoursRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findLatestForCandidateAndCours', 'findPassedCoursIdsForCandidate'])
            ->getMock();

        $courseCompleted = $this->buildCourse(1, 'Symfony Fondamentaux', 'Debutant', 'symfony,php', 6);
        $courseRecommended = $this->buildCourse(2, 'API Symfony', 'Intermediaire', 'symfony,api', 8);
        $courseUnrelated = $this->buildCourse(3, 'Docker Basics', 'Avance', 'docker,devops', 10);

        $coursRepository->method('findBy')->willReturn([$courseCompleted, $courseRecommended, $courseUnrelated]);
        $coursRepository->method('findDistinctNiveaux')->willReturn(['Debutant', 'Intermediaire', 'Avance']);

        $moduleRepository->method('findByCours')->willReturn([]);
        $leconRepository->method('findByCours')->willReturn([]);
        $quizRepository->method('findLatestForCandidateAndModule')->willReturn(null);

        $testRepository->method('findLatestForCandidateAndCours')->willReturn(null);
        $testRepository->method('findPassedCoursIdsForCandidate')->willReturn([1]);

        $service = new CandidateRecommendationService(
            $coursRepository,
            $moduleRepository,
            $leconRepository,
            $quizRepository,
            $testRepository,
        );

        $candidate = new User();
        $candidate->setId(10);

        $result = $service->buildForCandidate($candidate, [], 'Tous');

        self::assertArrayHasKey('recommendations', $result);
        self::assertNotEmpty($result['recommendations']);

        $first = $result['recommendations'][0];
        self::assertSame(2, $first['course']->getId());
        self::assertGreaterThan(0, $first['score']);
        self::assertNotEmpty($first['reasons']);
    }

    private function buildCourse(int $id, string $title, string $level, string $skills, int $duration): Cours
    {
        $course = new Cours();
        $course->setId($id);
        $course->setTitre($title);
        $course->setNiveau($level);
        $course->setCompetencesVisees($skills);
        $course->setDuree($duration);

        return $course;
    }
}

