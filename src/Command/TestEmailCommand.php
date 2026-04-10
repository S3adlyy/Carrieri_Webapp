<?php
// src/Command/TestEmailCommand.php

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestEmailCommand extends Command
{
    protected static $defaultName = 'app:test:email';
    protected static $defaultDescription = 'Test l\'envoi d\'email avec Gmail (PHPMailer)';

    public function __construct(private EmailService $emailService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test d\'envoi d\'email via Gmail (PHPMailer)');

        $toEmail = $io->ask('Email du destinataire pour le test', 'lwessaadli@gmail.com');

        $io->info('Tentative d\'envoi à : ' . $toEmail);

        $result = $this->emailService->sendInterviewNotification(
            toEmail: $toEmail,
            candidateName: 'Saadli Wassim',
            missionTitle: 'Mission Test Algorithmes',
            score: 85,
            interviewDate: date('d/m/Y') . ' à 14:30',
            jitsiLink: 'https://meet.jit.si/test-entretien-carrieri',
            interviewType: 'Technique',
            recruiterName: 'Recruteur Test'
        );

        if ($result) {
            $io->success('✅ Email envoyé avec succès à ' . $toEmail);
            $io->note('Vérifiez votre boîte de réception (et les spams)');
            return Command::SUCCESS;
        } else {
            $io->error('❌ Erreur lors de l\'envoi de l\'email');
            $io->error('Vérifiez que le mot de passe d\'application est correct');
            $io->error('Et que l\'option "Autoriser les applications moins sécurisées" est activée');
            return Command::FAILURE;
        }
    }
}