<?php

namespace App\Service;

use App\Entity\Reclamation;
use App\Entity\User;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReclamationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReclamationRepository $reclamationRepository,
        private MailerInterface $mailer
    ) {}

    /**
     * Compter les réclamations par statut pour un utilisateur
     */
    public function countByStatus(User $user): array
    {
        $reclamations = $this->reclamationRepository->findBy(['user' => $user]);
        
        $stats = [
            'en_attente' => 0,
            'en_cours' => 0,
            'traite' => 0,
            'total' => count($reclamations)
        ];
        
        foreach ($reclamations as $reclamation) {
            match($reclamation->getStatut()) {
                'En attente' => $stats['en_attente']++,
                'En cours' => $stats['en_cours']++,
                'Traité' => $stats['traite']++,
                default => null
            };
        }
        
        return $stats;
    }

    /**
     * Changer le statut d'une réclamation avec notification
     */
    public function changeStatus(Reclamation $reclamation, string $newStatus, ?string $commentaire = null): void
    {
        $oldStatus = $reclamation->getStatut();
        $reclamation->setStatut($newStatus);
        
        // Ajouter un historique si tu as une entité HistoriqueReclamation
        // $this->addHistory($reclamation, $oldStatus, $newStatus, $commentaire);
        
        $this->em->flush();
        
        // Envoyer une notification au candidat
        $this->sendStatusNotification($reclamation, $oldStatus, $newStatus);
    }

    /**
     * Envoyer une notification email au candidat
     */
    private function sendStatusNotification(Reclamation $reclamation, string $oldStatus, string $newStatus): void
    {
        $user = $reclamation->getUser();
        if (!$user) return;
        
        $email = (new Email())
            ->from('noreply@carrieri.com')
            ->to($user->getEmail())
            ->subject('Mise à jour de votre réclamation')
            ->html($this->renderEmailTemplate($reclamation, $oldStatus, $newStatus));
        
        $this->mailer->send($email);
    }

    private function renderEmailTemplate(Reclamation $reclamation, string $oldStatus, string $newStatus): string
    {
        return "
            <h1>Suivi de votre réclamation</h1>
            <p>Bonjour {$reclamation->getUser()->getFirstName()},</p>
            <p>Le statut de votre réclamation a changé :</p>
            <ul>
                <li><strong>Ancien statut :</strong> {$oldStatus}</li>
                <li><strong>Nouveau statut :</strong> {$newStatus}</li>
            </ul>
            <p>Récapitulatif de votre réclamation :</p>
            <p><strong>Objet :</strong> {$reclamation->getObjet()}</p>
            <p><strong>Description :</strong> {$reclamation->getDescription()}</p>
            <p>Cordialement,<br>L'équipe Carrieri</p>
        ";
    }

    /**
     * Obtenir le temps moyen de traitement des réclamations (en jours)
     */
    public function getAverageProcessingTime(): float
    {
        $traitees = $this->reclamationRepository->findBy(['statut' => 'Traité']);
        
        if (count($traitees) === 0) return 0;
        
        $totalDays = 0;
        foreach ($traitees as $reclamation) {
            // Logique de calcul (si tu as une date de traitement)
            // $totalDays += $reclamation->getDateTraitement()->diff($reclamation->getDateCreation())->days;
        }
        
        return $totalDays / count($traitees);
    }

    /**
     * Obtenir les réclamations urgentes (priorité Haute + non traitées)
     */
    public function getUrgentReclamations(): array
    {
        return $this->reclamationRepository->createQueryBuilder('r')
            ->where('r.priorite = :priorite')
            ->andWhere('r.statut != :statut')
            ->setParameter('priorite', 'Haute')
            ->setParameter('statut', 'Traité')
            ->orderBy('r.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }
}