<?php

namespace App\Controller\Api;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use App\Service\MailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/mail', name: 'api_mail_')]
class MailApiController extends AbstractController
{
    public function __construct(
        private MailService $mailService,
        private SerializerInterface $serializer
    ) {}

    /**
     * API: Envoyer un email au candidat après traitement
     * POST /api/mail/reclamation/{id}/notify
     */
    #[Route('/reclamation/{id}/notify', name: 'send_reclamation_notification', methods: ['POST'])]
    public function sendReclamationNotification(Reclamation $reclamation, Request $request): JsonResponse
    {
        // Vérifier les droits (admin ou recruteur)
        $user = $this->getUser();
        if (!$user || ($user->getType() !== 'ADMIN' && $user->getType() !== 'RECRUITER')) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        $data = json_decode($request->getContent(), true);
        $reponse = $data['reponse'] ?? '';
        $statutFinal = $data['statut_final'] ?? 'Résolu';
        
        if (empty($reponse)) {
            return $this->json(['error' => 'La réponse est obligatoire'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $this->mailService->sendReclamationTreatedEmail($reclamation, $reponse, $statutFinal);
            
            return $this->json([
                'success' => true,
                'message' => 'Email envoyé avec succès',
                'to' => $reclamation->getUser()?->getEmail()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'envoi',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API: Envoyer un email de confirmation de réclamation
     * POST /api/mail/reclamation/{id}/confirm
     */
    #[Route('/reclamation/{id}/confirm', name: 'send_reclamation_confirmation', methods: ['POST'])]
    public function sendReclamationConfirmation(Reclamation $reclamation): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || $user->getType() !== 'CANDIDATE') {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        // Vérifier que la réclamation appartient au candidat
        if ($reclamation->getUser() !== $user) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $this->mailService->sendReclamationConfirmationEmail($reclamation);
            
            return $this->json([
                'success' => true,
                'message' => 'Email de confirmation envoyé'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'envoi'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API: Tester l'envoi d'email
     * POST /api/mail/test
     */
    #[Route('/test', name: 'test_mail', methods: ['POST'])]
    public function testMail(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? $this->getUser()->getEmail();
        
        try {
            $testEmail = (new \Symfony\Component\Mime\Email())
                ->from('noreply@carrieri.com')
                ->to($email)
                ->subject('Test - Carrieri API Mail')
                ->html('<h1>Test réussi !</h1><p>Votre API d\'envoi d\'emails fonctionne correctement.</p>');
            
            $this->mailService->getMailer()->send($testEmail);
            
            return $this->json(['success' => true, 'message' => 'Email de test envoyé à ' . $email]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}