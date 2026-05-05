<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\AwsFaceRecognitionService;
use App\Service\AwsRekognitionConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

#[Route('/face')]
class AwsFaceController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private readonly AwsFaceRecognitionService $faceService,
        private readonly AwsRekognitionConfigService $awsConfig,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/aws-enroll', name: 'aws_face_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enrollFace(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
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
            return $this->json(['error' => $result['error'] ?? 'Face enrollment failed'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
    }

    #[Route('/aws-login', name: 'aws_face_login', methods: ['POST'])]
    public function loginWithFace(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $imageBase64 = $data['image'] ?? null;

            if (!$imageBase64) {
                return $this->json(['error' => 'No image provided'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->faceService->loginByFace($imageBase64);

            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'Face not recognized. Please try again with better lighting.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Manually log the user in
            $this->manualLogin($user, $request);

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
                'message' => 'Face recognized successfully!'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Face login exception', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/aws-disable', name: 'aws_face_disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disableFace(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $success = $this->faceService->deleteFace($user);

        if (!$success) {
            return $this->json(['error' => 'Failed to disable face login'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Face login disabled']);
    }

    #[Route('/aws-test-detect', name: 'aws_face_test_detect', methods: ['POST'])]
    public function testDetectFace(Request $request): JsonResponse
    {
        try {
            if ($request->files->has('image')) {
                $uploadedFile = $request->files->get('image');
                $imageBytes = file_get_contents($uploadedFile->getPathname());
            } else {
                $data = json_decode($request->getContent(), true);
                $imageBase64 = $data['image'] ?? null;
                if (!$imageBase64) {
                    return $this->json(['success' => false, 'error' => 'No image provided'], Response::HTTP_BAD_REQUEST);
                }
                $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
                $imageBytes = base64_decode($imageBase64);
            }

            $rekognition = $this->awsConfig->getRekognitionClient();

            $result = $rekognition->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);

            if (empty($result['FaceDetails'])) {
                return $this->json(['success' => false, 'error' => 'No face detected. Please ensure good lighting and center your face.']);
            }

            return $this->json([
                'success' => true,
                'faceCount' => count($result['FaceDetails']),
                'confidence' => $result['FaceDetails'][0]['Confidence'] ?? 0
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/aws-test', name: 'aws_face_test', methods: ['GET'])]
    public function testAwsApi(): JsonResponse
    {
        try {
            $rekognition = $this->awsConfig->getRekognitionClient();
            $collections = $rekognition->listCollections([]);

            return $this->json([
                'success' => true,
                'service' => 'AWS Rekognition',
                'region' => $this->awsConfig->getRegion(),
                'collection_id' => $this->awsConfig->getCollectionId(),
                'face_match_threshold' => $this->awsConfig->getFaceMatchThreshold(),
                'existing_collections' => $collections['CollectionIds'] ?? [],
                'message' => '✅ AWS Rekognition is configured correctly!'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'service' => 'AWS Rekognition',
                'error' => $e->getMessage(),
                'hint' => 'Make sure you have run "aws configure" in your terminal'
            ]);
        }
    }

    #[Route('/list-collections', name: 'list_collections', methods: ['GET'])]
    public function listCollections(): JsonResponse
    {
        try {
            $rekognition = $this->awsConfig->getRekognitionClient();
            $result = $rekognition->listCollections([]);

            return $this->json([
                'success' => true,
                'collections' => $result['CollectionIds'] ?? []
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/create-collection', name: 'create_collection', methods: ['GET'])]
    public function createCollection(): JsonResponse
    {
        try {
            $rekognition = $this->awsConfig->getRekognitionClient();
            $result = $rekognition->createCollection([
                'CollectionId' => $this->awsConfig->getCollectionId(),
            ]);

            return $this->json([
                'success' => true,
                'collection_id' => $this->awsConfig->getCollectionId(),
                'collection_arn' => $result['CollectionArn'],
                'message' => '✅ Collection created successfully!'
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ResourceAlreadyExistsException')) {
                return $this->json([
                    'success' => true,
                    'collection_id' => $this->awsConfig->getCollectionId(),
                    'message' => 'Collection already exists.'
                ]);
            }
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/aws-list-faces', name: 'aws_list_faces', methods: ['GET'])]
    public function listFaces(): JsonResponse
    {
        try {
            $rekognition = $this->awsConfig->getRekognitionClient();

            $result = $rekognition->listFaces([
                'CollectionId' => $this->awsConfig->getCollectionId(),
                'MaxResults' => 100,
            ]);

            $faces = [];
            foreach ($result['Faces'] as $face) {
                $faces[] = [
                    'faceId' => $face['FaceId'],
                    'externalImageId' => $face['ExternalImageId'] ?? null,
                    'confidence' => $face['Confidence'] ?? null,
                ];
            }

            $users = $this->entityManager->getRepository(User::class)->findAll();
            $userMap = [];
            foreach ($users as $user) {
                if ($user->getFacePersonId()) {
                    $userMap[$user->getFacePersonId()] = [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'face_enabled' => $user->getFaceEnabled()
                    ];
                }
            }

            return $this->json([
                'success' => true,
                'collection_id' => $this->awsConfig->getCollectionId(),
                'face_count' => count($faces),
                'faces' => $faces,
                'user_mapping' => $userMap
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/clear-collection', name: 'clear_collection', methods: ['GET'])]
    public function clearCollection(): JsonResponse
    {
        try {
            $rekognition = $this->awsConfig->getRekognitionClient();

            $faces = $rekognition->listFaces([
                'CollectionId' => $this->awsConfig->getCollectionId(),
                'MaxResults' => 100,
            ]);

            $deletedCount = 0;
            foreach ($faces['Faces'] as $face) {
                $rekognition->deleteFaces([
                    'CollectionId' => $this->awsConfig->getCollectionId(),
                    'FaceIds' => [$face['FaceId']]
                ]);
                $deletedCount++;
            }

            $users = $this->entityManager->getRepository(User::class)->findAll();
            foreach ($users as $user) {
                $user->setFacePersonId(null);
                $user->setFaceEnabled(0);
            }
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'deleted_faces' => $deletedCount,
                'updated_users' => count($users),
                'message' => 'Collection cleared and user face data reset'
            ]);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Manually authenticate a user
     */
    private function manualLogin(User $user, Request $request): void
    {
        // Update last login time
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Create authentication token
        $token = new UsernamePasswordToken(
            $user,
            'main',  // firewall name - make sure this matches your security.yaml
            $user->getRoles()
        );

        // Set token in security storage using injected service
        $this->tokenStorage->setToken($token);

        // Dispatch login event using injected service
        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch($event);
    }
}
