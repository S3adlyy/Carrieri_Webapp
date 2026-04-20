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

    /**
     * Send verification code email for registration
     */
    public function sendVerificationCode(string $toEmail, string $toName, string $verificationCode): bool
    {
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

            // Sender and recipient
            $mail->setFrom($this->senderEmail, $this->senderName);
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "🔐 Verify Your Email - Carrieri";
            $mail->Body    = $this->buildVerificationEmailHtml($toName, $verificationCode);
            $mail->AltBody = $this->buildVerificationEmailText($toName, $verificationCode);

            $mail->send();
            $this->logger->info('Verification email sent successfully', ['to' => $toEmail]);
            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to send verification email: ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Build HTML template for verification email
     */
    private function buildVerificationEmailHtml(string $name, string $code): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Carrieri</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .content { padding: 40px 30px; text-align: center; }
        .code { font-size: 48px; font-weight: bold; color: #667eea; letter-spacing: 10px; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 12px; font-family: 'Courier New', monospace; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px; font-size: 14px; color: #856404; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .expiry { color: #ef4444; font-size: 13px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Carrieri</h1>
            <p>Vérification de votre adresse email</p>
        </div>
        <div class="content">
            <h2>Bonjour {$name} !</h2>
            <p>Merci de vous être inscrit sur Carrieri. Pour finaliser votre inscription, veuillez utiliser le code de vérification ci-dessous :</p>
            
            <div class="code">{$code}</div>
            
            <p>Ce code est valable pendant <strong>15 minutes</strong>.</p>
            
            <div class="warning">
                <strong>⚠️ Important :</strong> Ne partagez jamais ce code avec personne. Notre équipe ne vous le demandera jamais.
            </div>
            
            <p>Si vous n'avez pas créé de compte sur Carrieri, veuillez ignorer cet email.</p>
        </div>
        <div class="footer">
            <p>© 2024 Carrieri - Tous droits réservés</p>
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build plain text template for verification email
     */
    private function buildVerificationEmailText(string $name, string $code): string
    {
        return "
========================================
      VÉRIFICATION EMAIL - CARRIERI
========================================

Bonjour $name,

Merci de vous être inscrit sur Carrieri.

Votre code de vérification est : $code

Ce code expirera dans 15 minutes.

⚠️ Important : Ne partagez jamais ce code avec personne.

Si vous n'avez pas créé de compte sur Carrieri, veuillez ignorer cet email.

Cordialement,
L'équipe Carrieri
========================================
";
    }

    // Your existing sendInterviewNotification method remains here
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

    // Your existing buildHtmlEmail and buildTextEmail methods remain here
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