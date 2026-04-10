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

        // Générer le PDF
        $filename = $this->generateCertificatePDF($user, $cours);

        if ($filename === null) {
            return false;
        }

        $certification->setCheminFichier('/certificates/' . $filename);

        $this->entityManager->persist($certification);
        $this->entityManager->flush();

        // Notify the candidate once, only when the certificate is newly created.
        $this->sendCompletionEmail($user, $cours);

        return true;
    }

    private function sendCompletionEmail(User $user, Cours $cours): void
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

        $filename = $this->generateCertificatePDF($user, $cours);
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
    public function generateCertificatePDF(User $user, Cours $cours): ?string
    {
        try {
            // Créer le répertoire s'il n'existe pas
            $this->filesystem->mkdir($this->certificatesDir, 0755);

            // Générer le nom du fichier
            $filename = $this->generateFilename($user, $cours);
            $filepath = $this->certificatesDir . '/' . $filename;

            // Générer le HTML du certificat
            $html = $this->generateCertificateHTML($user, $cours);

            // Configurer DOMPDF
            $options = new Options();
            $options->set('defaultFont', 'Helvetica');
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('dpi', 96);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Sauvegarder le fichier
            $bytes = file_put_contents($filepath, $dompdf->output());
            if ($bytes === false || $bytes <= 0 || !is_file($filepath)) {
                throw new \RuntimeException('Echec d\'ecriture du PDF de certificat sur le disque.');
            }

            return $filename;
        } catch (\Exception $e) {
            error_log('Erreur lors de la génération du certificat: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère le HTML du certificat
     */
    private function generateCertificateHTML(User $user, Cours $cours): string
    {
        $displayName = trim($user->getFirstName() . ' ' . $user->getLastName());
        if (empty($displayName)) {
            $displayName = $user->getEmail() ?? 'Candidat';
        }

        $dateobtention = (new \DateTimeImmutable())->format('d/m/Y');
        $certNumber = 'CERT-' . date('YmdHis');
        $courseTitle = htmlspecialchars((string) ($cours->getTitre() ?? 'Cours'), ENT_QUOTES, 'UTF-8');
        $safeDisplayName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $nameClass = mb_strlen($displayName) > 26 ? 'recipient-name--compact' : '';
        $courseClass = mb_strlen((string) ($cours->getTitre() ?? '')) > 46 ? 'course-name--compact' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificat d'achèvement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            background: #ffffff;
            color: #1f2937;
            overflow: hidden;
        }

        .certificate {
            width: 180mm;
            height: 260mm;
            margin: 0;
            border: 1px solid #4f46e5;
            background: #ffffff;
            padding: 4mm 5mm;
            text-align: center;
            position: relative;
            page-break-inside: avoid;
            page-break-after: avoid;
            overflow: visible;
        }

        .certificate-content {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .header {
            margin-bottom: 4px;
        }

        .logo-text {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #312e81;
        }

        .divider {
            width: 80px;
            height: 2px;
            background-color: #4f46e5;
            margin: 4px auto;
        }

        .body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            margin: 4px 0 2px;
        }

        .certificate-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.2;
            color: #111827;
        }

        .certificate-text {
            font-size: 10px;
            line-height: 1.15;
            margin-bottom: 3px;
            color: #374151;
        }

        .recipient-name {
            font-size: 17px;
            font-weight: bold;
            margin: 3px 0;
            text-decoration: underline;
            text-decoration-color: #4f46e5;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
            line-height: 1.15;
            overflow-wrap: break-word;
            color: #111827;
        }

        .recipient-name--compact {
            font-size: 15px;
        }

        .course-name {
            font-size: 12px;
            margin: 3px 0;
            font-weight: 600;
            line-height: 1.25;
            overflow-wrap: break-word;
            color: #4338ca;
        }

        .course-name--compact {
            font-size: 11px;
        }

        .footer {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 2px;
            padding-top: 4px;
            border-top: 1px solid #d1d5db;
            page-break-inside: avoid;
        }

        .signature-area {
            display: table-cell;
            width: 33.333%;
            text-align: left;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            width: 80px;
            margin-bottom: 4px;
        }

        .signature-text {
            font-size: 8px;
            color: #6b7280;
        }

        .date-and-number {
            display: table-cell;
            width: 33.333%;
            text-align: center;
            vertical-align: bottom;
        }

        .date-number-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
            opacity: 0.9;
        }

        .date-number-value {
            font-size: 9px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .footer-logo {
            display: table-cell;
            width: 33.333%;
            text-align: right;
            font-size: 9px;
            font-weight: bold;
            color: #312e81;
            line-height: 1.2;
            vertical-align: bottom;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-content">
            <div class="header">
                <div class="logo-text">CARRIERI</div>
                <div class="divider"></div>
            </div>

            <div class="body">
                <div class="certificate-title">CERTIFICAT D'ACHEVEMENT</div>
                
                <div class="certificate-text">
                    Ce certificat est décerné à
                </div>

                <div class="recipient-name {$nameClass}">
                    {$safeDisplayName}
                </div>

                <div class="certificate-text">
                    pour avoir complété avec succès le cours
                </div>

                <div class="course-name {$courseClass}">
                    {$courseTitle}
                </div>
            </div>

            <div class="footer">
                <div class="signature-area">
                    <div class="signature-line"></div>
                    <div class="signature-text">Signature</div>
                </div>

                <div class="date-and-number">
                    <div class="date-number-label">Date</div>
                    <div class="date-number-value">{$dateobtention}</div>
                    <div class="date-number-label">N°</div>
                    <div class="date-number-value">{$certNumber}</div>
                </div>

                <div class="footer-logo">
                    CARRIERI<br>
                    <span style="font-size: 11px; font-weight: normal; letter-spacing: 0;">Plateforme de Formation</span>
                </div>
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
}













