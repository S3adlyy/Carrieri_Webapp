<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/candidat/asl-interview')]
#[IsGranted('ROLE_CANDIDAT')]
class AslInterviewController extends AbstractController
{
    // URL of the running Python ASL server
    private const ASL_SERVER = 'http://localhost:5001';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    /**
     * Main ASL interview page
     */
    #[Route('', name: 'app_asl_interview')]
    public function index(): Response
    {
        // Check if the Python ASL server is reachable
        $serverOnline = false;
        try {
            $response = $this->httpClient->request('GET', self::ASL_SERVER . '/health', ['timeout' => 2]);
            $serverOnline = $response->getStatusCode() === 200;
        } catch (\Throwable) {
            $serverOnline = false;
        }

        return $this->render('FrontOffice/asl_interview/asl_interview_index.html.twig', [
            'asl_server_url' => self::ASL_SERVER,
            'server_online'  => $serverOnline,
        ]);
    }

    /**
     * Proxy: get current gesture from Python server
     * Called by JS every ~300ms
     */
    #[Route('/api/gesture', name: 'app_asl_gesture', methods: ['GET'])]
    public function gesture(): JsonResponse
    {
        try {
            $response = $this->httpClient->request('GET', self::ASL_SERVER . '/api/gesture', ['timeout' => 1]);
            return new JsonResponse($response->getContent(), $response->getStatusCode(), [], true);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'ASL server unreachable: ' . $e->getMessage()], 503);
        }
    }

    /**
     * Save a completed ASL interview session to the database (optional)
     */
    #[Route('/api/save-session', name: 'app_asl_save_session', methods: ['POST'])]
    public function saveSession(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Example: log the session — extend to persist via Doctrine if needed
        // $session = new AslInterviewSession();
        // $session->setUser($this->getUser());
        // $session->setRecognizedText($data['text'] ?? '');
        // $em->persist($session); $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Session saved',
            'text'    => $data['text'] ?? '',
        ]);
    }
}
