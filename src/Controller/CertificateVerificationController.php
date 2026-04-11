<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CertificationService;
use App\Service\CertificateModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CertificateVerificationController extends AbstractController
{
    public function __construct(
        private CertificationService $certificationService,
        private CertificateModerationService $certificateModerationService,
    )
    {
    }

    #[Route('/certificats/verifier/{id}', name: 'app_public_certificate_verify', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function verify(Request $request, int $id): Response
    {
        $certificate = $this->certificationService->getCertificate($id);
        if ($certificate === null) {
            return $this->render('public/certificate_verification.html.twig', [
                'is_valid' => false,
                'reason' => 'Certificat introuvable.',
                'certificate' => null,
                'course' => null,
            ]);
        }

        $sig = (string) $request->query->get('sig', '');
        $isValid = $this->certificationService->isVerificationSignatureValid($certificate, $sig);
        $state = $this->certificateModerationService->getCertificateState($certificate);

        if ($state['status'] === 'invalid') {
            $isValid = false;
        }

        $reason = null;
        if ($state['status'] === 'invalid') {
            $reason = $state['reason'] !== ''
                ? (string) $state['reason']
                : 'Certificat non valide (fraude signalee).';
        } elseif (!$isValid) {
            $reason = 'Signature invalide ou lien modifie.';
        }

        return $this->render('public/certificate_verification.html.twig', [
            'is_valid' => $isValid,
            'reason' => $reason,
            'certificate' => $certificate,
            'course' => $certificate->getCours(),
        ]);
    }
}

