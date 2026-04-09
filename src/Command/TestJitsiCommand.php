<?php
// src/Command/TestJitsiCommand.php

namespace App\Command;

use App\Service\JitsiLinkGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestJitsiCommand extends Command
{
    protected static $defaultName = 'app:test:jitsi';
    protected static $defaultDescription = 'Test la génération de liens Jitsi';

    public function __construct(
        private JitsiLinkGenerator $jitsiGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test de génération de liens Jitsi');

        // Test 1: Lien d'entretien
        $io->section('Lien pour entretien');
        $entretienLink = $this->jitsiGenerator->generateMeetingLink(123, 456, 789);
        $io->writeln('🔗 ' . $entretienLink);

        // Test 2: Lien de test simple
        $io->section('Lien de test');
        $testLink = $this->jitsiGenerator->generateTestRoom();
        $io->writeln('🔗 ' . $testLink);

        $io->success('Liens générés avec succès !');
        $io->note('Vous pouvez ouvrir ces liens dans votre navigateur pour tester Jitsi');

        return Command::SUCCESS;
    }
}