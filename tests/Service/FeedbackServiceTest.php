<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Feedback;
use App\Entity\User;
use App\Repository\FeedbackRepository;
use App\Service\FeedbackService;
use PHPUnit\Framework\TestCase;

final class FeedbackServiceTest extends TestCase
{
    public function testGetAverageNoteForRecruiterReturnsZeroWhenNoFeedback(): void
    {
        $recruiter = $this->createMock(User::class);

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn([]);

        $service = new FeedbackService($repository);

        $result = $service->getAverageNoteForRecruiter($recruiter);

        $this->assertSame(0.0, $result);
    }

    public function testGetAverageNoteForRecruiterReturnsRoundedAverage(): void
    {
        $recruiter = $this->createMock(User::class);

        $feedback1 = $this->createMock(Feedback::class);
        $feedback1->method('getNote')->willReturn(4);

        $feedback2 = $this->createMock(Feedback::class);
        $feedback2->method('getNote')->willReturn(5);

        $feedback3 = $this->createMock(Feedback::class);
        $feedback3->method('getNote')->willReturn(3);

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn([$feedback1, $feedback2, $feedback3]);

        $service = new FeedbackService($repository);

        $result = $service->getAverageNoteForRecruiter($recruiter);

        $this->assertSame(4.0, $result);
    }

    public function testGetNoteDistributionReturnsCorrectCounts(): void
    {
        $recruiter = $this->createMock(User::class);

        $feedback1 = $this->createMock(Feedback::class);
        $feedback1->method('getNote')->willReturn(5);

        $feedback2 = $this->createMock(Feedback::class);
        $feedback2->method('getNote')->willReturn(5);

        $feedback3 = $this->createMock(Feedback::class);
        $feedback3->method('getNote')->willReturn(3);

        $feedback4 = $this->createMock(Feedback::class);
        $feedback4->method('getNote')->willReturn(1);

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn([$feedback1, $feedback2, $feedback3, $feedback4]);

        $service = new FeedbackService($repository);

        $result = $service->getNoteDistribution($recruiter);

        $this->assertSame([
            1 => 1,
            2 => 0,
            3 => 1,
            4 => 0,
            5 => 2,
        ], $result);
    }

    public function testGetNoteDistributionIgnoresInvalidNotes(): void
    {
        $recruiter = $this->createMock(User::class);

        $feedback1 = $this->createMock(Feedback::class);
        $feedback1->method('getNote')->willReturn(0);

        $feedback2 = $this->createMock(Feedback::class);
        $feedback2->method('getNote')->willReturn(6);

        $feedback3 = $this->createMock(Feedback::class);
        $feedback3->method('getNote')->willReturn(4);

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn([$feedback1, $feedback2, $feedback3]);

        $service = new FeedbackService($repository);

        $result = $service->getNoteDistribution($recruiter);

        $this->assertSame([
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 1,
            5 => 0,
        ], $result);
    }

    public function testGetLatestFeedbacksReturnsRepositoryResult(): void
    {
        $recruiter = $this->createMock(User::class);

        $feedback1 = $this->createMock(Feedback::class);
        $feedback2 = $this->createMock(Feedback::class);
        $latest = [$feedback1, $feedback2];

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $recruiter],
                ['createdAt' => 'DESC'],
                5
            )
            ->willReturn($latest);

        $service = new FeedbackService($repository);

        $result = $service->getLatestFeedbacks($recruiter);

        $this->assertSame($latest, $result);
    }

    public function testGetLatestFeedbacksUsesCustomLimit(): void
    {
        $recruiter = $this->createMock(User::class);

        $latest = [$this->createMock(Feedback::class)];

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $recruiter],
                ['createdAt' => 'DESC'],
                3
            )
            ->willReturn($latest);

        $service = new FeedbackService($repository);

        $result = $service->getLatestFeedbacks($recruiter, 3);

        $this->assertSame($latest, $result);
    }

    public function testGetStatsReturnsAllCalculatedValues(): void
    {
        $recruiter = $this->createMock(User::class);

        $feedback1 = $this->createMock(Feedback::class);
        $feedback1->method('getNote')->willReturn(5);

        $feedback2 = $this->createMock(Feedback::class);
        $feedback2->method('getNote')->willReturn(4);

        $feedback3 = $this->createMock(Feedback::class);
        $feedback3->method('getNote')->willReturn(4);

        $feedback4 = $this->createMock(Feedback::class);
        $feedback4->method('getNote')->willReturn(2);

        $allFeedbacks = [$feedback1, $feedback2, $feedback3, $feedback4];

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->exactly(2))
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn($allFeedbacks);

        $service = new FeedbackService($repository);

        $result = $service->getStats($recruiter);

        $this->assertSame(3.8, $result['note_moyenne']);
        $this->assertSame(4, $result['total_feedbacks']);
        $this->assertSame([
            1 => 0,
            2 => 1,
            3 => 0,
            4 => 2,
            5 => 1,
        ], $result['distribution']);
        $this->assertSame(5, $result['meilleure_note']);
        $this->assertSame(2, $result['pire_note']);
    }

    public function testGetStatsReturnsZeroValuesWhenNoFeedback(): void
    {
        $recruiter = $this->createMock(User::class);

        $repository = $this->createMock(FeedbackRepository::class);
        $repository->expects($this->exactly(2))
            ->method('findBy')
            ->with(['user' => $recruiter])
            ->willReturn([]);

        $service = new FeedbackService($repository);

        $result = $service->getStats($recruiter);

        $this->assertSame(0.0, $result['note_moyenne']);
        $this->assertSame(0, $result['total_feedbacks']);
        $this->assertSame([
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ], $result['distribution']);
        $this->assertSame(0, $result['meilleure_note']);
        $this->assertSame(0, $result['pire_note']);
    }
}