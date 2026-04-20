<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\FaceRecognitionService;
use App\Service\AzureFaceClientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Psr\Log\LoggerInterface;

#[Route('/face')]
class FaceController extends AbstractController
{
    public function __construct(
        private readonly FaceRecognitionService $faceService,
        private readonly AzureFaceClientService $azureFaceClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/enroll', name: 'face_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enrollFace(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $imageBase64 = $data['image'] ?? null;

        if (!$imageBase64) {
            return $this->json(['error' => 'No image provided'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->faceService->enrollFace($user, $imageBase64);

        if (!$result['success']) {
            return $this->json(['error' => $result['error']], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
    }
    #[Route('/test-api', name: 'test_face_api', methods: ['GET'])]
    public function testApi(): Response
    {
        // Hardcode the working credentials from Java
        $endpoint = "https://carrieri-face-id.cognitiveservices.azure.com";
        $key = "BjHMnlvGZug7CujVef7emHSKmpyudb5ZS1n52ZzHgOQhvG7y0a93JQQJ99CCAC5RqLJXJ3w3AAAKACOGQwxw";

        $results = [];

        // Test 1: Check API connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$endpoint/face/v1.0/detect?returnFaceId=true");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Ocp-Apim-Subscription-Key: $key",
            "Content-Type: application/octet-stream"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results['api_test'] = [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response' => $response,
            'works' => ($httpCode == 400 || $httpCode == 200)
        ];

        // Test 2: Check .env values
        $results['env_values'] = [
            'AZURE_FACE_ENDPOINT' => $_ENV['AZURE_FACE_ENDPOINT'] ?? 'NOT SET',
            'AZURE_FACE_KEY' => isset($_ENV['AZURE_FACE_KEY']) ? substr($_ENV['AZURE_FACE_KEY'], 0, 20) . '...' : 'NOT SET',
            'AZURE_FACE_PERSON_GROUP_ID' => $_ENV['AZURE_FACE_PERSON_GROUP_ID'] ?? 'NOT SET',
        ];

        // Test 3: Check if .env file exists and is readable
        $envFile = $this->getParameter('kernel.project_dir') . '/.env';
        $results['env_file'] = [
            'exists' => file_exists($envFile),
            'readable' => is_readable($envFile),
            'path' => $envFile
        ];

        // Test 4: Read .env file content (first few lines)
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            $lines = explode("\n", $content);
            $azureLines = [];
            foreach ($lines as $line) {
                if (str_contains($line, 'AZURE_FACE')) {
                    $azureLines[] = $line;
                }
            }
            $results['env_azure_lines'] = $azureLines;
        }

        return $this->json($results);
    }

    #[Route('/login', name: 'face_login', methods: ['POST'])]
    public function loginWithFace(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $imageBase64 = $data['image'] ?? null;

            $this->logger->info('Face login attempt', ['has_image' => !empty($imageBase64)]);

            if (!$imageBase64) {
                return $this->json(['error' => 'No image provided'], Response::HTTP_BAD_REQUEST);
            }

            // Try to identify the user by face
            $user = $this->faceService->loginByFace($imageBase64);

            if (!$user) {
                $this->logger->warning('Face login failed - no user recognized');
                return $this->json([
                    'success' => false,
                    'error' => 'Face not recognized. Please try again with better lighting.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Check if user is active
            if (!$user->getIsActive()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Your account is not active. Please contact support.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Manually log the user in
            $this->manualLogin($user, $request);

            $this->logger->info('Face login successful', ['userId' => $user->getId()]);

            // Determine redirect URL based on user type
            $redirectUrl = $this->generateUrl('app_home');
            if ($user->getType() === 'CANDIDATE') {
                $redirectUrl = $this->generateUrl('app_candidate_main');
            } elseif ($user->getType() === 'RECRUITER') {
                $redirectUrl = $this->generateUrl('app_home');
            }

            return $this->json([
                'success' => true,
                'redirectUrl' => $redirectUrl,
                'message' => 'Face recognized successfully! Redirecting...'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Face login exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Server error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/disable', name: 'face_disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disableFace(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $success = $this->faceService->deleteFace($user);

        if (!$success) {
            return $this->json(['error' => 'Failed to disable face login'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Face login disabled']);
    }

    #[Route('/status', name: 'face_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function faceStatus(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->faceService->getFaceStatus($user));
    }

    #[Route('/test-detect', name: 'face_test_detect', methods: ['POST'])]
    public function testDetectFace(Request $request): JsonResponse
    {
        try {
            // Get uploaded file
            $uploadedFile = $request->files->get('image');

            if (!$uploadedFile) {
                // Also try to get from JSON if sent as base64
                $data = json_decode($request->getContent(), true);
                $imageBase64 = $data['image'] ?? null;

                if ($imageBase64) {
                    $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
                    $imageBytes = base64_decode($imageBase64);
                } else {
                    return $this->json(['success' => false, 'error' => 'No image provided'], Response::HTTP_BAD_REQUEST);
                }
            } else {
                // Get raw image bytes from uploaded file
                $imageBytes = file_get_contents($uploadedFile->getPathname());
            }

            // Log image info for debugging
            $this->logger->info('Testing face detection', [
                'image_size' => strlen($imageBytes),
                'image_size_kb' => round(strlen($imageBytes) / 1024, 2)
            ]);

            // Call Azure Face API directly (bypass the client for testing)
            $endpoint = $_ENV['AZURE_FACE_ENDPOINT'];
            $key = $_ENV['AZURE_FACE_KEY'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint . '/face/v1.0/detect?returnFaceId=true&detectionModel=detection_03&recognitionModel=recognition_04');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Ocp-Apim-Subscription-Key: ' . $key,
                'Content-Type: application/octet-stream'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $imageBytes);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $this->logger->info('Azure Face API response', [
                'http_code' => $httpCode,
                'response' => $response
            ]);

            if ($httpCode !== 200) {
                return $this->json([
                    'success' => false,
                    'error' => "API Error (HTTP $httpCode): " . ($curlError ?: $response)
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($response, true);

            if (empty($data) || !is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No face detected. Make sure you are looking directly at the camera with good lighting.'
                ]);
            }

            return $this->json([
                'success' => true,
                'faceId' => $data[0]['faceId'],
                'faceCount' => count($data)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Face test detection failed', ['error' => $e->getMessage()]);
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manually authenticate a user
     */
    private function manualLogin(User $user, Request $request): void
    {
        // Create authentication token
        $token = new UsernamePasswordToken(
            $user,
            'main',  // firewall name
            $user->getRoles()
        );

        // Set token in security context
        $this->container->get('security.token_storage')->setToken($token);

        // Dispatch login event
        $event = new InteractiveLoginEvent($request, $token);
        $this->container->get('event_dispatcher')->dispatch($event);
    }
}