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

            $html = $this->twig->render('emails/certificate_completed.html.twig', [
                'display_name' => $displayName,
                'course_title' => $courseTitle,
                'certificates_url' => $certificatesUrl,
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
        $displayName = trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName());
        if ($displayName === '') {
            $displayName = (string) ($user->getEmail() ?? 'Candidat');
        }

        $certNumber = $certificate?->getId() !== null
            ? 'CERT-' . $certificate->getId()
            : 'CERT-' . date('YmdHis');
        $courseTitle = htmlspecialchars((string) ($cours->getTitre() ?? 'Cours'), ENT_QUOTES, 'UTF-8');
        $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $verificationUrl = $certificate instanceof Certification ? $this->getPublicVerificationUrl($certificate) : null;
        $qrImageUrl = $verificationUrl !== null ? $this->buildQrImageUrl($verificationUrl) : null;
        $qrHtml = '';

        if ($qrImageUrl !== null) {
            $safeQrImage = htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8');
            $qrHtml = <<<QRCODE
                <div class="qr-box">
                    <img src="{$safeQrImage}" alt="QR Code de vérification" class="qr-image">
                    <div class="qr-title">QR de vérification</div>
                    <div class="qr-subtitle">Scan rapide du certificat</div>
                </div>
QRCODE;
        }

        $dateObj = $certificate?->getDateObtention() ?? new \DateTimeImmutable();
        $formattedDate = $dateObj->format('d/m/Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Certificat d'achèvement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page { size: A4 landscape; margin: 0; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            background: #f7f7fb;
            width: 100%;
        }

        .page {
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(99,102,241,0.16), transparent 28%),
                radial-gradient(circle at bottom right, rgba(168,85,247,0.14), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #fbfbff 100%);
        }

        .frame {
            position: absolute;
            top: 12mm;
            left: 12mm;
            right: 12mm;
            bottom: 12mm;
            border: 1.4px solid #c7d2fe;
        }

        .frame-inner {
            position: absolute;
            top: 17mm;
            left: 17mm;
            right: 17mm;
            bottom: 17mm;
            border: 1px solid #e5e7eb;
        }

        .top-band {
            position: absolute;
            top: 20mm;
            left: 20mm;
            right: 20mm;
            height: 24mm;
            background: linear-gradient(120deg, #312e81 0%, #4f46e5 45%, #7c3aed 100%);
            border-radius: 8px;
            color: #fff;
            padding: 7mm 10mm 0 10mm;
        }

        .brand {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            opacity: .88;
        }

        .title {
            margin-top: 1mm;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        .title strong { display: block; font-size: 34px; line-height: 1.05; }

        .seal {
            position: absolute;
            top: 52mm;
            right: 36mm;
            width: 34mm;
            height: 34mm;
            border-radius: 50%;
            border: 2px solid rgba(79,70,229,0.25);
            background: radial-gradient(circle at 35% 35%, #fff 0%, #eef2ff 72%, #e0e7ff 100%);
            text-align: center;
            padding-top: 7mm;
            color: #4338ca;
            box-shadow: 0 10px 30px rgba(79,70,229,0.12);
        }

        .seal .emoji { font-size: 22px; display: block; }
        .seal .small { font-size: 7px; margin-top: 1.5mm; letter-spacing: 1.2px; text-transform: uppercase; }

        .body {
            position: absolute;
            top: 78mm;
            left: 24mm;
            right: 24mm;
            text-align: center;
        }

        .label {
            font-size: 11px;
            letter-spacing: 3px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .recipient {
            margin-top: 4mm;
            font-size: 31px;
            font-family: Georgia, 'Times New Roman', serif;
            font-weight: 700;
            color: #111827;
        }

        .course-line {
            margin-top: 5mm;
            font-size: 14px;
            color: #374151;
        }

        .course-name {
            display: inline-block;
            margin-top: 3mm;
            padding: 3mm 7mm;
            font-size: 21px;
            font-weight: 700;
            color: #4f46e5;
            background: rgba(79,70,229,0.07);
            border: 1px solid rgba(79,70,229,0.15);
            border-radius: 999px;
            max-width: 240mm;
        }

        .summary {
            margin: 8mm auto 0 auto;
            max-width: 175mm;
            font-size: 11.5px;
            line-height: 1.7;
            color: #6b7280;
        }

        .bottom-row {
            position: absolute;
            left: 24mm;
            right: 24mm;
            bottom: 24mm;
            display: table;
            width: calc(100% - 0mm);
        }

        .sign, .qr-col, .meta {
            display: table-cell;
            vertical-align: bottom;
            width: 33.33%;
        }

        .sign { text-align: left; }
        .meta { text-align: right; }
        .qr-col { text-align: center; }

        .line {
            width: 62mm;
            border-top: 1px solid #9ca3af;
            margin-bottom: 3mm;
        }

        .sign-name {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }

        .sign-role {
            font-size: 9px;
            color: #6b7280;
            margin-top: 1mm;
        }

        .meta-box {
            display: inline-block;
            text-align: right;
            padding: 4mm 5mm;
            background: rgba(255,255,255,0.7);
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            min-width: 56mm;
        }

        .meta-label {
            font-size: 8.5px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .meta-value {
            margin-top: 1mm;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }

        .cert-number {
            margin-top: 1mm;
            font-size: 9px;
            color: #9ca3af;
        }

        .qr-box {
            display: inline-block;
            text-align: center;
            padding: 3mm;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .qr-image {
            width: 46mm;
            height: 46mm;
            display: block;
            border-radius: 8px;
        }

        .qr-title {
            margin-top: 2mm;
            font-size: 9px;
            font-weight: 700;
            color: #111827;
        }

        .qr-subtitle {
            margin-top: 1mm;
            font-size: 7.5px;
            color: #6b7280;
        }

        .footer-note {
            position: absolute;
            left: 24mm;
            right: 24mm;
            bottom: 16mm;
            text-align: center;
            font-size: 8.5px;
            color: #9ca3af;
            letter-spacing: .4px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="frame"></div>
        <div class="frame-inner"></div>

        <div class="top-band">
            <div class="brand">Carrieri Academy</div>
            <div class="title">CERTIFICAT <strong>D'ACHÈVEMENT</strong></div>
        </div>

        <div class="seal">
            <span class="emoji">🎓</span>
            <span class="small">Officiel</span>
        </div>

        <div class="body">
            <div class="label">Ce certificat est décerné à</div>
            <div class="recipient">{$safeDisplayName}</div>
            <div class="course-line">pour avoir complété avec succès le cours</div>
            <div class="course-name">{$courseTitle}</div>
            <div class="summary">
                Ce document atteste que l’apprenant a suivi le parcours complet, validé les modules associés et réussi l’évaluation finale conformément aux exigences de la plateforme Carrieri.
            </div>
        </div>

        <div class="bottom-row">
            <div class="sign">
                <div class="line"></div>
                <div class="sign-name">Carrieri</div>
                <div class="sign-role">Plateforme de formation</div>
            </div>

            <div class="qr-col">
                {$qrHtml}
            </div>

            <div class="meta">
                <div class="meta-box">
                    <div class="meta-label">Date d'obtention</div>
                    <div class="meta-value">{$formattedDate}</div>
                    <div class="cert-number">N° {$certNumber}</div>
                </div>
            </div>
        </div>

        <div class="footer-note">Certificat généré automatiquement • Vérification via QR • Carrieri</div>
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













