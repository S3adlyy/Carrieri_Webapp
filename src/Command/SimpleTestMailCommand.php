<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mail', description: 'Tester l\'envoi d\'email')]
class SimpleTestMailCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse email du destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destinataire = $input->getArgument('email');
        
        $output->writeln('<info>📧 Envoi d\'un email de test...</info>');
        $output->writeln('Destinataire : ' . $destinataire);
        
        // 🔥 REMPLACE TON MOT DE PASSE ICI (sans espaces)
        $motDePasse = 'jzjcvckzzlsvuasu'; // ← ton mot de passe d'application
        
        $dsn = 'smtp://selim.benabdelkader@esprit.tn:' . $motDePasse . '@smtp.gmail.com:587';
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        
        $email = (new Email())
            ->from('selim.benabdelkader@esprit.tn')  // ← expéditeur doit être ton email Gmail
            ->to($destinataire)
            ->subject('Test Carrieri Mail')
            ->html('<h1>Test réussi !</h1><p>Votre configuration mailer fonctionne correctement.</p>');

        try {
            $mailer->send($email);
            $output->writeln('<info>✅ Email envoyé avec succès à ' . $destinataire . ' !</info>');
            $output->writeln('Vérifie dans ta boîte Gmail (regarde aussi les spams)');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}