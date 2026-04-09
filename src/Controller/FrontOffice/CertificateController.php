<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Repository\CertificationRepository;
use App\Service\CertificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/candidat')]
#[IsGranted('ROLE_CANDIDAT')]
class CertificateController extends AbstractController
{
    public function __construct(
        private CertificationService $certificationService,
        #[Autowire('%kernel.project_dir%/public/certificates')]
        private string $certificatesDir,
    ) {
    }

    #[Route('/mes-certificats', name: 'app_candidate_certificates')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $certificates = $this->certificationService->getCertificatesByUser($user);

        // Traiter les données pour l'affichage
        $certificateData = [];
        foreach ($certificates as $cert) {
            $certId = $cert->getId();
            if ($certId === null) {
                continue;
            }

            $fullPath = $this->certificationService->ensureCertificateFile($cert);
            $course = $cert->getCours();

            $certificateData[] = [
                'id' => $certId,
                'course_title' => $course?->getTitre() ?? 'Cours inconnu',
                'course_description' => $course?->getDescription() ?? '',
                'date_obtained' => $cert->getDateObtention(),
                'certificate_number' => 'CERT-' . $certId,
                'has_file' => $fullPath !== null && file_exists($fullPath),
            ];
        }

        return $this->render('FrontOffice/main/certificates.html.twig', [
            'certificates' => $certificateData,
            'total_certificates' => count($certificateData),
        ]);
    }

    #[Route('/certificat/{id}/telecharger', name: 'app_candidate_certificate_download', requirements: ['id' => '\\d+'])]
    public function download(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $certificate = $this->certificationService->getCertificate($id);
        if (!$certificate) {
            throw $this->createNotFoundException('Certificat non trouvé');
        }

        // Vérifier que l'utilisateur est le propriétaire
        if ($certificate->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        $fullPath = $this->certificationService->ensureCertificateFile($certificate);
        if (!$fullPath) {
            throw $this->createNotFoundException('Fichier du certificat non disponible');
        }

        $course = $certificate->getCours();
        $courseTitle = $course ? ($course->getTitre() ?? 'certificat') : 'certificat';
        $filename = 'Certificat_' . str_replace(' ', '_', $courseTitle) . '_' . date('Ymd') . '.pdf';

        return new BinaryFileResponse($fullPath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/certificat/{id}/voir', name: 'app_candidate_certificate_view', requirements: ['id' => '\\d+'])]
    public function view(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $certificate = $this->certificationService->getCertificate($id);
        if (!$certificate) {
            throw $this->createNotFoundException('Certificat non trouvé');
        }

        // Vérifier que l'utilisateur est le propriétaire
        if ($certificate->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        $fullPath = $this->certificationService->ensureCertificateFile($certificate);
        if (!$fullPath) {
            throw $this->createNotFoundException('Fichier du certificat non disponible');
        }

        return new BinaryFileResponse($fullPath, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    #[Route('/certificat/{id}', name: 'app_candidate_certificate_show', requirements: ['id' => '\\d+'])]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $certificate = $this->certificationService->getCertificate($id);
        if (!$certificate) {
            throw $this->createNotFoundException('Certificat non trouvé');
        }

        // Vérifier que l'utilisateur est le propriétaire
        if ($certificate->getUser() !== $user) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        $course = $certificate->getCours();
        
        return $this->render('FrontOffice/main/certificate_detail.html.twig', [
            'certificate' => $certificate,
            'course' => $course,
        ]);
    }
}



