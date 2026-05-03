<?php

namespace App\Service;

use App\Entity\Reclamation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendReclamationTreatedEmail(Reclamation $reclamation, string $reponse, string $statutFinal, ?string $emailDestinataire, ?string $prenom = null): void
    {
        $prenom = $prenom ?: 'Cher candidat';
        $color = $statutFinal === 'Résolu' ? '#28a745' : '#ffc107';
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4472C4; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; border: 1px solid #ddd; }
                .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
                .status { font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Carrieri</h1>
                    <p>Votre réclamation a été traitée</p>
                </div>
                <div class='content'>
                    <h2>Bonjour {$prenom},</h2>
                    <p>Nous vous informons que votre réclamation a été traitée.</p>
                    <hr>
                    <p><strong>Objet :</strong> {$reclamation->getObjet()}</p>
                    <p><strong>Description :</strong> {$reclamation->getDescription()}</p>
                    <p><strong>Réponse :</strong> {$reponse}</p>
                    <p><strong>Statut :</strong> <span style='color:{$color}' class='status'>{$statutFinal}</span></p>
                    <hr>
                    <p>Cordialement,<br>L'équipe Carrieri</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Carrieri - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $email = (new Email())
            ->from('selim.benabdelkader@esprit.tn')
            ->to($emailDestinataire)
            ->subject('Votre réclamation a été traitée - Carrieri')
            ->html($htmlContent);
        
        $this->mailer->send($email);
    }
}