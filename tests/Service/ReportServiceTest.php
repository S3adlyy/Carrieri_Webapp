<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Feedback;
use App\Entity\Reclamation;
use App\Repository\FeedbackRepository;
use App\Repository\ReclamationRepository;
use App\Service\ReportService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class ReportServiceTest extends TestCase
{
    public function testGenerateReclamationReportReturnsGroupedData(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-01-31');

        $reclamation1 = $this->createMock(Reclamation::class);
        $reclamation1->method('getStatut')->willReturn('en_attente');
        $reclamation1->method('getPriorite')->willReturn('haute');

        $reclamation2 = $this->createMock(Reclamation::class);
        $reclamation2->method('getStatut')->willReturn('traitee');
        $reclamation2->method('getPriorite')->willReturn('moyenne');

        $reclamation3 = $this->createMock(Reclamation::class);
        $reclamation3->method('getStatut')->willReturn('en_attente');
        $reclamation3->method('getPriorite')->willReturn('haute');

        $reclamations = [$reclamation1, $reclamation2, $reclamation3];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($reclamations);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('where')
            ->with('r.dateCreation BETWEEN :start AND :end')
            ->willReturnSelf();

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnMap([
                ['start', $start, $qb],
                ['end', $end, $qb],
            ]);

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $reclamationRepository = $this->createMock(ReclamationRepository::class);
        $reclamationRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($qb);

        $feedbackRepository = $this->createMock(FeedbackRepository::class);

        $service = new ReportService($reclamationRepository, $feedbackRepository);

        $result = $service->generateReclamationReport($start, $end);

        $this->assertSame([
            'start' => '01/01/2025',
            'end' => '31/01/2025',
        ], $result['period']);

        $this->assertSame(3, $result['total']);
        $this->assertSame([
            'en_attente' => 2,
            'traitee' => 1,
        ], $result['by_status']);
        $this->assertSame([
            'haute' => 2,
            'moyenne' => 1,
        ], $result['by_priority']);
        $this->assertSame($reclamations, $result['reclamations']);
    }

    public function testGenerateReclamationReportHandlesNullStatusAndPriority(): void
    {
        $start = new \DateTimeImmutable('2025-02-01');
        $end = new \DateTimeImmutable('2025-02-28');

        $reclamation = $this->createMock(Reclamation::class);
        $reclamation->method('getStatut')->willReturn(null);
        $reclamation->method('getPriorite')->willReturn(null);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$reclamation]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $reclamationRepository = $this->createMock(ReclamationRepository::class);
        $reclamationRepository->method('createQueryBuilder')->willReturn($qb);

        $feedbackRepository = $this->createMock(FeedbackRepository::class);

        $service = new ReportService($reclamationRepository, $feedbackRepository);

        $result = $service->generateReclamationReport($start, $end);

        $this->assertSame(['' => 1], $result['by_status']);
        $this->assertSame(['' => 1], $result['by_priority']);
    }

    public function testGenerateFeedbackReportReturnsStatistics(): void
    {
        $start = new \DateTimeImmutable('2025-03-01');
        $end = new \DateTimeImmutable('2025-03-31');

        $feedback1 = $this->createMock(Feedback::class);
        $feedback1->method('getNote')->willReturn(4);

        $feedback2 = $this->createMock(Feedback::class);
        $feedback2->method('getNote')->willReturn(5);

        $feedback3 = $this->createMock(Feedback::class);
        $feedback3->method('getNote')->willReturn(3);

        $feedbacks = [$feedback1, $feedback2, $feedback3];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($feedbacks);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('where')
            ->with('f.createdAt BETWEEN :start AND :end')
            ->willReturnSelf();

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnMap([
                ['start', $start, $qb],
                ['end', $end, $qb],
            ]);

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $reclamationRepository = $this->createMock(ReclamationRepository::class);

        $feedbackRepository = $this->createMock(FeedbackRepository::class);
        $feedbackRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('f')
            ->willReturn($qb);

        $service = new ReportService($reclamationRepository, $feedbackRepository);

        $result = $service->generateFeedbackReport($start, $end);

        $this->assertSame([
            'start' => '01/03/2025',
            'end' => '31/03/2025',
        ], $result['period']);

        $this->assertSame(3, $result['total']);
        $this->assertSame(4.0, $result['note_moyenne']);
        $this->assertSame(3, $result['note_min']);
        $this->assertSame(5, $result['note_max']);
        $this->assertSame($feedbacks, $result['feedbacks']);
    }

    public function testGenerateFeedbackReportReturnsZeroStatsWhenEmpty(): void
    {
        $start = new \DateTimeImmutable('2025-04-01');
        $end = new \DateTimeImmutable('2025-04-30');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $reclamationRepository = $this->createMock(ReclamationRepository::class);

        $feedbackRepository = $this->createMock(FeedbackRepository::class);
        $feedbackRepository->method('createQueryBuilder')->willReturn($qb);

        $service = new ReportService($reclamationRepository, $feedbackRepository);

        $result = $service->generateFeedbackReport($start, $end);

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['note_moyenne']);
        $this->assertSame(0, $result['note_min']);
        $this->assertSame(0, $result['note_max']);
        $this->assertSame([], $result['feedbacks']);
    }
}