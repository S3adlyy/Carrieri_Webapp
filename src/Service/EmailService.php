<?php
// src/Service/EmailService.php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;

class EmailService
{
    private string $senderEmail;
    private string $senderName;
    private string $gmailPassword;
    private LoggerInterface $logger;

    public function __construct(
        string $senderEmail,
        string $senderName,
        string $gmailPassword,
        LoggerInterface $logger
    ) {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->gmailPassword = $gmailPassword;
        $this->logger = $logger;
    }

    public function sendInterviewNotification(
        string $toEmail,
        string $candidateName,
        string $missionTitle,
        float $score,
        string $interviewDate,
        string $jitsiLink,
        string $interviewType,
        string $recruiterName
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // Configuration SMTP Gmail
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->senderEmail;
            $mail->Password   = $this->gmailPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Expéditeur et destinataire
            $mail->setFrom($this->senderEmail, $this->senderName);
            $mail->addAddress($toEmail);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = "📅 Entretien programmé - Mission: $missionTitle";
            $mail->Body    = $this->buildHtmlEmail($candidateName, $missionTitle, $score, $interviewDate, $jitsiLink, $interviewType, $recruiterName);
            $mail->AltBody = $this->buildTextEmail($candidateName, $missionTitle, $score, $interviewDate, $jitsiLink, $interviewType, $recruiterName);

            $mail->send();
            $this->logger->info('Email envoyé avec succès', ['to' => $toEmail]);
            return true;

        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email: ' . $mail->ErrorInfo);
            return false;
        }
    }

    private function buildHtmlEmail(string $candidateName, string $missionTitle, float $score, string $interviewDate, string $jitsiLink, string $interviewType, string $recruiterName): string
    {
        $scorePercent = round($score);
        $scoreColor = $scorePercent >= 60 ? '#10b981' : '#ef4444';
        $scoreText = $scorePercent >= 60 ? '✅ Accepté' : '❌ Refusé';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Entretien programmé</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .score-box { background: $scoreColor; color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
        .score-number { font-size: 48px; font-weight: bold; }
        .info-box { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #667eea; }
        .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Entretien programmé</h1>
            <p>Félicitations pour votre réussite !</p>
        </div>
        <div class="content">
            <h2>Bonjour $candidateName,</h2>
            <p>Félicitations ! Votre solution pour la mission <strong>$missionTitle</strong> a été évaluée.</p>
            
            <div class="score-box">
                <div class="score-number">$scorePercent%</div>
                <div>Score obtenu • $scoreText</div>
                <div style="font-size: 12px;">Seuil requis: 60%</div>
            </div>
            
            <div class="info-box">
                <strong>🎯 Informations de l'entretien</strong><br>
                Type: $interviewType<br>
                Date: $interviewDate<br>
                Recruteur: $recruiterName
            </div>
            
            <div class="info-box">
                <strong>🔗 Lien de visioconférence</strong><br>
                <a href="$jitsiLink" target="_blank">$jitsiLink</a><br><br>
                <a href="$jitsiLink" class="btn" target="_blank">🎥 Rejoindre l'entretien</a>
            </div>
            
            <div class="info-box">
                <strong>📋 Conseils pour l'entretien</strong>
                <ul>
                    <li>Testez votre caméra et micro avant l'entretien</li>
                    <li>Préparez-vous à discuter de votre solution technique</li>
                    <li>Soyez ponctuel et professionnel</li>
                </ul>
            </div>
            
            <p>Cordialement,<br><strong>L'équipe Carrieri</strong></p>
        </div>
        <div class="footer">
            <p>© 2024 Carrieri - Plateforme de recrutement technique</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildTextEmail(string $candidateName, string $missionTitle, float $score, string $interviewDate, string $jitsiLink, string $interviewType, string $recruiterName): string
    {
        $scorePercent = round($score);
        $scoreText = $scorePercent >= 60 ? 'ACCEPTÉ' : 'REFUSÉ';

        return "
========================================
      ENTRETIEN PROGRAMMÉ
========================================

Bonjour $candidateName,

Félicitations ! Votre solution pour la mission $missionTitle a été évaluée.

📊 RÉSULTAT
Score obtenu : $scorePercent% - $scoreText
Seuil requis : 60%

🎯 INFORMATIONS DE L'ENTRETIEN
Type : $interviewType
Date : $interviewDate
Recruteur : $recruiterName

🔗 LIEN DE VISIOCONFÉRENCE
$jitsiLink

📋 CONSEILS POUR L'ENTRETIEN
- Testez votre caméra et micro avant l'entretien
- Préparez-vous à discuter de votre solution technique
- Soyez ponctuel et professionnel

Cordialement,
L'équipe Carrieri
";
    }
}