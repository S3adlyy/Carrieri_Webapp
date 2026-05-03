<?php

namespace App\Command;

use App\Repository\ReclamationRepository;
use App\Service\MailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:quick-mail-test', description: 'Test rapide email')]
class QuickMailTestCommand extends Command
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private MailService $mailService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupérer la dernière réclamation
        $reclamation = $this->reclamationRepository->findOneBy([], ['id' => 'DESC']);
        
        if (!$reclamation) {
            $output->writeln('<error>Aucune réclamation trouvée</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('=====================================');
        $output->writeln('TEST ENVOI EMAIL');
        $output->writeln('=====================================');
        $output->writeln('Réclamation ID: ' . $reclamation->getId());
        $output->writeln('Objet: ' . $reclamation->getObjet());
        $output->writeln('Email candidat: ' . ($reclamation->getUser()?->getEmail() ?? 'NON TROUVE'));
        $output->writeln('-------------------------------------');
        
        try {
            $this->mailService->sendReclamationTreatedEmail(
                $reclamation, 
                'Ceci est un test de la commande Symfony', 
                'Résolu',
                null,
                null
            );
            $output->writeln('<info>✅ Email envoyé avec succès !</info>');
            $output->writeln('Vérifie dans Mailtrap : https://mailtrap.io');
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('=====================================');
        
        return Command::SUCCESS;
    }
}