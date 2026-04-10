<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Certification;
use App\Entity\Cours;
use App\Entity\User;
use App\Repository\CertificationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class CertificationService
{
    private Filesystem $filesystem;

    public function __construct(
        private CertificationRepository $certificationRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
        #[Autowire('%kernel.project_dir%/public/certificates')]
        private string $certificatesDir,
        #[Autowire('%env(SENDER_EMAIL)%')]
        private string $senderEmail,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Crée un certificat si l'utilisateur a complété le cours
     * Retourne true si un certificat a été créé, false sinon
     */
    public function createCertificateIfCompleted(User $user, Cours $cours, int $progress): bool
    {
        if ($progress < 100) {
            return false;
        }

        $userId = $user->getId();
        $coursId = $cours->getId();

        if ($userId === null || $coursId === null) {
            return false;
        }

        // Vérifier si un certificat existe déjà
        $existingCert = $this->certificationRepository->findOneBy([
            'user' => $user,
            'cours' => $cours,
        ]);

        if ($existingCert !== null) {
            return false; // Certificat déjà généré
        }

        // Créer la certification
        $certification = new Certification();
        $certification->setUser($user);
        $certification->setCours($cours);
        $certification->setCandidatId($userId);
        $certification->setCoursId($coursId);
        $certification->setDateObtention(new \DateTimeImmutable());

        // First render before persistence (no verification QR yet).
        $filename = $this->generateCertificatePDF($user, $cours);

        if ($filename === null) {
            return false;
        }

        $certification->setCheminFichier('/certificates/' . $filename);

        $this->entityManager->persist($certification);
        $this->entityManager->flush();

        // Regenerate once the certificate has an ID to embed a signed verification QR in the PDF.
        $this->regenerateCertificateFile($certification);

        // Notify the candidate once, only when the certificate is newly created.
        $this->sendCompletionEmail($user, $cours, $certification);

        return true;
    }

    private function sendCompletionEmail(User $user, Cours $cours, Certification $certification): void
    {
        $recipient = (string) ($user->getEmail() ?? '');
        if ($recipient === '') {
            return;
        }

        $displayName = trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName());
        if ($displayName === '') {
            $displayName = $recipient;
        }

        $courseTitle = (string) ($cours->getTitre() ?? 'votre cours');

        try {
            $certificatesUrl = $this->urlGenerator->generate(
                'app_candidate_certificates',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $verificationUrl = $this->getPublicVerificationUrl($certification);

            $html = $this->twig->render('emails/certificate_completed.html.twig', [
                'display_name' => $displayName,
                'course_title' => $courseTitle,
                'certificates_url' => $certificatesUrl,
                'verification_url' => $verificationUrl,
                'year' => (new \DateTimeImmutable())->format('Y'),
            ]);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($recipient)
                ->subject('Felicitations ! Votre certificat est disponible')
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Keep certificate creation successful even if SMTP fails.
            $this->logger->warning('Email de felicitation non envoye apres creation du certificat.', [
                'user_id' => $user->getId(),
                'cours_id' => $cours->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ensureCertificateFile(Certification $certificate): ?string
    {
        $filepath = $certificate->getCheminFichier();
        if ($filepath !== null) {
            $fullPath = $this->certificatesDir . '/' . basename($filepath);
            if (is_file($fullPath)) {
                return $fullPath;
            }
        }

        return $this->regenerateCertificateFile($certificate);
    }

    public function regenerateCertificateFile(Certification $certificate): ?string
    {
        [$user, $cours] = $this->resolveCertificateContext($certificate);
        if (!$user instanceof User || !$cours instanceof Cours) {
            return null;
        }

        if ($certificate->getUser() === null) {
            $certificate->setUser($user);
        }
        if ($certificate->getCours() === null) {
            $certificate->setCours($cours);
        }

        $filename = $this->generateCertificatePDF($user, $cours, $certificate);
        if ($filename === null) {
            return null;
        }

        $certificate->setCheminFichier('/certificates/' . $filename);
        $this->entityManager->flush();

        return $this->certificatesDir . '/' . $filename;
    }

    /**
     * @return array{0: ?User, 1: ?Cours}
     */
    private function resolveCertificateContext(Certification $certificate): array
    {
        $user = $certificate->getUser();
        if (!$user instanceof User && $certificate->getCandidatId() !== null) {
            $user = $this->entityManager->find(User::class, $certificate->getCandidatId());
        }

        $cours = $certificate->getCours();
        if (!$cours instanceof Cours && $certificate->getCoursId() !== null) {
            $cours = $this->entityManager->find(Cours::class, $certificate->getCoursId());
        }

        return [$user, $cours];
    }

    /**
     * Génère le PDF du certificat et retourne le nom du fichier
     */
    public function generateCertificatePDF(User $user, Cours $cours, ?Certification $certificate = null): ?string
    {
        try {
            $this->filesystem->mkdir($this->certificatesDir, 0755);

            $filename = $this->generateFilename($user, $cours);
            $filepath = $this->certificatesDir . '/' . $filename;

            $html = $this->generateCertificateHTML($user, $cours, $certificate);

            $options = new Options();
            $options->set('defaultFont', 'Helvetica');
            $options->set('isPhpEnabled', false);
            $options->set('isRemoteEnabled', true);
            $options->set('dpi', 96);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $bytes = file_put_contents($filepath, $dompdf->output());
            if ($bytes === false || $bytes <= 0 || !is_file($filepath)) {
                throw new \RuntimeException('Echec d\'ecriture du PDF de certificat.');
            }

            return $filename;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de generation PDF certificat.', [
                'user_id' => $user->getId(),
                'cours_id' => $cours->getId(),
                'certificate_id' => $certificate?->getId(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Génère le HTML du certificat
     */
    private function generateCertificateHTML(User $user, Cours $cours, ?Certification $certificate = null): string
    {
        $displayName = trim($user->getFirstName() . ' ' . $user->getLastName());
        if (empty($displayName)) {
            $displayName = $user->getEmail() ?? 'Candidat';
        }

        $certNumber = $certificate?->getId() !== null
            ? 'CERT-' . $certificate->getId()
            : 'CERT-' . date('YmdHis');
        $courseTitle = htmlspecialchars((string) ($cours->getTitre() ?? 'Cours'), ENT_QUOTES, 'UTF-8');
        $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $verificationUrl = $certificate instanceof Certification ? $this->getPublicVerificationUrl($certificate) : null;
        $qrImageUrl = $verificationUrl !== null ? $this->buildQrImageUrl($verificationUrl) : null;

        $qrHtml = '';
        if ($qrImageUrl !== null && $verificationUrl !== null) {
            $safeQrImage = htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8');
            $safeVerification = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
            $qrHtml = <<<QRCODE
            <div class="verification-qr">
                <img src="{$safeQrImage}" alt="QR Code" class="verification-qr-image">
                <div class="verification-text">Verifier ce certificat</div>
                <div class="verification-link">{$safeVerification}</div>
            </div>
QRCODE;
        }

        $dateObj = $certificate?->getDateObtention() ?? new \DateTimeImmutable();
        $formattedDate = $dateObj->format('F d, Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Certificat d'achèvement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            background: #ffffff;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 297mm;
            height: 210mm;
            position: relative;
            background: #ffffff;
            page-break-after: avoid;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        .border-outer {
            position: absolute;
            top: 15mm;
            left: 15mm;
            right: 15mm;
            bottom: 15mm;
            border: 1px solid #4f46e5;
        }

        .border-inner {
            position: absolute;
            top: 20mm;
            left: 20mm;
            right: 20mm;
            bottom: 20mm;
            border: 1px solid #e5e7eb;
        }

        /* Header en haut */
        .header {
            position: absolute;
            top: 30mm;
            left: 0;
            right: 0;
            text-align: center;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 4px;
            color: #312e81;
            margin-bottom: 15px;
        }

        .certificate-title {
            font-size: 32px;
            font-weight: 300;
            color: #1f2937;
            letter-spacing: 2px;
        }

        .certificate-title strong {
            font-weight: 700;
            display: block;
            font-size: 40px;
            margin-top: 5px;
        }

        /* Body centré verticalement */
        .body {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            text-align: center;
            padding: 0 40px;
        }

        .awarded-to {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .recipient-name {
            font-size: 42px;
            font-weight: bold;
            color: #111827;
            margin: 15px 0;
            font-family: 'Georgia', serif;
        }

        .completion-text {
            font-size: 16px;
            color: #6b7280;
            margin: 20px 0 10px;
        }

        .course-name {
            font-size: 26px;
            font-weight: 600;
            color: #4f46e5;
            margin: 15px 0;
            font-family: 'Georgia', serif;
        }

        .hours-badge {
            background: #f3f4f6;
            display: inline-block;
            padding: 6px 20px;
            border-radius: 50px;
            font-size: 12px;
            color: #6b7280;
            margin-top: 15px;
        }

        /* Footer tout en bas */
        .footer {
            position: absolute;
            bottom: 30mm;
            left: 25mm;
            right: 25mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-section {
            text-align: left;
            flex: 1;
        }

        .signature-line {
            width: 150px;
            border-top: 1px solid #9ca3af;
            margin-bottom: 8px;
        }

        .signature-name {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        .signature-title {
            font-size: 9px;
            color: #9ca3af;
        }

        .verification-section {
            text-align: center;
            flex: 1;
        }

        .verification-qr {
            display: inline-block;
            text-align: center;
        }

        .verification-qr-image {
            width: 65px;
            height: 65px;
            border: 1px solid #e5e7eb;
            padding: 3px;
            background: #ffffff;
        }

        .verification-text {
            margin-top: 5px;
            font-size: 9px;
            color: #6b7280;
            font-weight: 600;
        }

        .verification-link {
            font-size: 7px;
            color: #9ca3af;
            word-break: break-all;
            max-width: 200px;
            margin-top: 3px;
        }

        .date-section {
            text-align: right;
            flex: 1;
        }

        .date-label {
            font-size: 10px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .date-value {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .certificate-number {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 5px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .page {
                margin: 0;
                padding: 0;
                page-break-after: avoid;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            @page {
                size: A4 landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="border-outer"></div>
        <div class="border-inner"></div>
        
        <div class="header">
            <div class="logo">CARRIERI</div>
            <div class="certificate-title">
                CERTIFICAT<br>
                <strong>D'ACHÈVEMENT</strong>
            </div>
        </div>

        <div class="body">
            <div class="awarded-to">Ce certificat est décerné à</div>
            <div class="recipient-name">{$safeDisplayName}</div>
            <div class="completion-text">pour avoir complété avec succès le cours</div>
            <div class="course-name">{$courseTitle}</div>
            <div class="hours-badge">Formation complétée avec succès</div>
        </div>

        <div class="footer">
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-name">Bilal El Eter</div>
                <div class="signature-title">Executive Director, CARRIERI</div>
            </div>

            <div class="verification-section">
                {$qrHtml}
            </div>

            <div class="date-section">
                <div class="date-label">Date d'obtention</div>
                <div class="date-value">{$formattedDate}</div>
                <div class="certificate-number">N° {$certNumber}</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Génère un nom de fichier unique pour le certificat
     */
    private function generateFilename(User $user, Cours $cours): string
    {
        $timestamp = date('YmdHis');
        $userId = $user->getId() ?? 0;
        $coursId = $cours->getId() ?? 0;
        
        return sprintf('cert_%d_%d_%s.pdf', $userId, $coursId, $timestamp);
    }

    /**
     * Récupère les certificats d'un utilisateur
     */
    public function getCertificatesByUser(User $user): array
    {
        return $this->certificationRepository->findBy(
            ['user' => $user],
            ['dateObtention' => 'DESC']
        );
    }

    /**
     * Récupère un certificat spécifique
     */
    public function getCertificate(int $id): ?Certification
    {
        return $this->certificationRepository->find($id);
    }

    public function getPublicVerificationUrl(Certification $certificate): ?string
    {
        if ($certificate->getId() === null) {
            return null;
        }

        $signature = $this->buildVerificationSignature($certificate);

        return $this->urlGenerator->generate('app_public_certificate_verify', [
            'id' => $certificate->getId(),
            'sig' => $signature,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isVerificationSignatureValid(Certification $certificate, ?string $providedSignature): bool
    {
        if ($providedSignature === null || $providedSignature === '') {
            return false;
        }

        $expected = $this->buildVerificationSignature($certificate);

        return hash_equals($expected, $providedSignature);
    }

    private function buildVerificationSignature(Certification $certificate): string
    {
        $payload = implode('|', [
            (string) ($certificate->getId() ?? 0),
            (string) ($certificate->getCandidatId() ?? 0),
            (string) ($certificate->getCoursId() ?? 0),
            (string) ($certificate->getDateObtention()?->format('c') ?? ''),
        ]);

        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    private function buildQrImageUrl(string $value): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($value);
    }
}













