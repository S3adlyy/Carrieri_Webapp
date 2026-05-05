<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reclamation;
use App\Entity\User;
use App\Repository\ReclamationRepository;
use App\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ReclamationServiceTest extends TestCase
{
    public function testCountByStatusReturnsCorrectStats(): void
    {
        $user = $this->createMock(User::class);

        $r1 = $this->createMock(Reclamation::class);
        $r1->method('getStatut')->willReturn('En attente');

        $r2 = $this->createMock(Reclamation::class);
        $r2->method('getStatut')->willReturn('En cours');

        $r3 = $this->createMock(Reclamation::class);
        $r3->method('getStatut')->willReturn('Traité');

        $r4 = $this->createMock(Reclamation::class);
        $r4->method('getStatut')->willReturn('En attente');

        $repository = $this->createMock(ReclamationRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user])
            ->willReturn([$r1, $r2, $r3, $r4]);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $service = new ReclamationService($em, $repository, $mailer);

        $result = $service->countByStatus($user);

        $this->assertSame([
            'en_attente' => 2,
            'en_cours' => 1,
            'traite' => 1,
            'total' => 4,
        ], $result);
    }

    public function testCountByStatusIgnoresUnknownStatuses(): void
    {
        $user = $this->createMock(User::class);

        $r1 = $this->createMock(Reclamation::class);
        $r1->method('getStatut')->willReturn('Autre');

        $repository = $this->createMock(ReclamationRepository::class);
        $repository->method('findBy')->willReturn([$r1]);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $service = new ReclamationService($em, $repository, $mailer);

        $result = $service->countByStatus($user);

        $this->assertSame([
            'en_attente' => 0,
            'en_cours' => 0,
            'traite' => 0,
            'total' => 1,
        ], $result);
    }

    public function testChangeStatusFlushesAndSendsNotificationWhenUserExists(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getFirstName')->willReturn('Ahmed');

        $reclamation = $this->createMock(Reclamation::class);
        $reclamation->expects($this->once())
            ->method('getStatut')
            ->willReturn('En attente');

        $reclamation->expects($this->once())
            ->method('setStatut')
            ->with('En cours');

        $reclamation->method('getUser')->willReturn($user);
        $reclamation->method('getObjet')->willReturn('Objet test');
        $reclamation->method('getDescription')->willReturn('Description test');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $repository = $this->createMock(ReclamationRepository::class);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertSame('Mise à jour de votre réclamation', $email->getSubject());

                $to = $email->getTo();
                $this->assertCount(1, $to);
                $this->assertSame('user@example.com', $to[0]->getAddress());

                $html = $email->getHtmlBody() ?? '';
                $this->assertStringContainsString('Ahmed', $html);
                $this->assertStringContainsString('En attente', $html);
                $this->assertStringContainsString('En cours', $html);
                $this->assertStringContainsString('Objet test', $html);
                $this->assertStringContainsString('Description test', $html);

                return true;
            }));

        $service = new ReclamationService($em, $repository, $mailer);

        $service->changeStatus($reclamation, 'En cours');
    }

    public function testChangeStatusFlushesWithoutSendingEmailWhenUserIsNull(): void
    {
        $reclamation = $this->createMock(Reclamation::class);
        $reclamation->method('getStatut')->willReturn('En attente');
        $reclamation->expects($this->once())
            ->method('setStatut')
            ->with('Traité');
        $reclamation->method('getUser')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $repository = $this->createMock(ReclamationRepository::class);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())
            ->method('send');

        $service = new ReclamationService($em, $repository, $mailer);

        $service->changeStatus($reclamation, 'Traité');
    }

    public function testGetAverageProcessingTimeReturnsZeroWhenNoTreatedReclamations(): void
    {
        $repository = $this->createMock(ReclamationRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['statut' => 'Traité'])
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $service = new ReclamationService($em, $repository, $mailer);

        $result = $service->getAverageProcessingTime();

        $this->assertSame(0.0, $result);
    }

    public function testGetAverageProcessingTimeReturnsZeroWithCurrentImplementation(): void
    {
        $reclamation = $this->createMock(Reclamation::class);

        $repository = $this->createMock(ReclamationRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['statut' => 'Traité'])
            ->willReturn([$reclamation]);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $service = new ReclamationService($em, $repository, $mailer);

        $result = $service->getAverageProcessingTime();

        $this->assertSame(0.0, $result);
    }

    public function testGetUrgentReclamationsReturnsRepositoryQueryResult(): void
    {
        $urgentReclamations = [
            $this->createMock(Reclamation::class),
            $this->createMock(Reclamation::class),
        ];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($urgentReclamations);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('where')
            ->with('r.priorite = :priorite')
            ->willReturnSelf();

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('r.statut != :statut')
            ->willReturnSelf();

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnMap([
                ['priorite', 'Haute', $qb],
                ['statut', 'Traité', $qb],
            ]);

        $qb->expects($this->once())
            ->method('orderBy')
            ->with('r.dateCreation', 'ASC')
            ->willReturnSelf();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(ReclamationRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('r')
            ->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $service = new ReclamationService($em, $repository, $mailer);

        $result = $service->getUrgentReclamations();

        $this->assertSame($urgentReclamations, $result);
    }
}