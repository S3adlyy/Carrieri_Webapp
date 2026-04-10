<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Certification;
use App\Entity\Cours;
use App\Entity\User;
use App\Repository\CertificationRepository;
use App\Service\CertificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class CertificationServiceTest extends TestCase
{
    public function testCreateCertificateReturnsFalseWhenProgressIsBelow100(): void
    {
        $repository = $this->getMockBuilder(CertificationRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $twig = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $service = new CertificationService(
            $repository,
            $entityManager,
            $mailer,
            $logger,
            $twig,
            $urlGenerator,
            sys_get_temp_dir(),
            'noreply@example.com'
        );

        $user = new User();
        $user->setId(1);

        $cours = new Cours();
        $cours->setId(10);

        self::assertFalse($service->createCertificateIfCompleted($user, $cours, 99));
    }

    public function testCreateCertificateReturnsFalseWhenCertificateAlreadyExists(): void
    {
        $repository = $this->getMockBuilder(CertificationRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repository->method('findOneBy')->willReturn(new Certification());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $twig = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $service = new CertificationService(
            $repository,
            $entityManager,
            $mailer,
            $logger,
            $twig,
            $urlGenerator,
            sys_get_temp_dir(),
            'noreply@example.com'
        );

        $user = new User();
        $user->setId(1);

        $cours = new Cours();
        $cours->setId(10);

        self::assertFalse($service->createCertificateIfCompleted($user, $cours, 100));
    }
}

