<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CertificationRepository;
use App\Service\CertificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:certificates:regenerate-missing-files',
    description: 'Regenerate missing certificate PDF files from existing certification records.',
)]
final class RegenerateCertificatesCommand extends Command
{
    public function __construct(
        private CertificationRepository $certificationRepository,
        private CertificationService $certificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Regenerate all certificate PDFs even if a file already exists.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $certificates = $this->certificationRepository->findBy([], ['id' => 'ASC']);
        if ($certificates === []) {
            $io->success('No certifications found.');

            return Command::SUCCESS;
        }

        $processed = 0;
        $regenerated = 0;
        $failed = 0;

        foreach ($certificates as $certificate) {
            $processed++;
            $existingPath = $certificate->getCheminFichier();

            $fullPath = $force
                ? $this->certificationService->regenerateCertificateFile($certificate)
                : $this->certificationService->ensureCertificateFile($certificate);

            if ($fullPath === null) {
                $failed++;
                $io->warning(sprintf('Certificate #%d: unable to generate PDF.', (int) $certificate->getId()));
                continue;
            }

            if ($force || $existingPath === null || $existingPath === '') {
                $regenerated++;
                continue;
            }

            $expectedFullPath = dirname($fullPath) . DIRECTORY_SEPARATOR . basename($existingPath);
            if (!is_file($expectedFullPath)) {
                $regenerated++;
            }
        }

        $io->success(sprintf(
            'Processed %d certificate(s): %d regenerated, %d failed.',
            $processed,
            $regenerated,
            $failed
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}


