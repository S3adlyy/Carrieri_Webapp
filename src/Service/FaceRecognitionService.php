<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FaceRecognitionService
{
    public function __construct(
        private readonly AzureFaceClientService $azureFaceClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        // Initialize person group on service creation
        try {
            $this->azureFaceClient->createPersonGroupIfNotExists();
            $this->logger->info('Face recognition service initialized');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize face service', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Enroll a user's face for future recognition
     */
    public function enrollFace(User $user, string $imageBase64): array
    {
        try {
            // First, detect if there's a face in the image
            $faceId = $this->azureFaceClient->detectFace($imageBase64);

            if (!$faceId) {
                return [
                    'success' => false,
                    'error' => 'No face detected. Please ensure good lighting and center your face.'
                ];
            }

            // Create a person for this user
            $personName = $user->getFirstName() . ' ' . $user->getLastName();
            $personId = $this->azureFaceClient->createPerson($user->getId(), $personName);

            if (!$personId) {
                return [
                    'success' => false,
                    'error' => 'Failed to create face profile.'
                ];
            }

            // Add face to the person
            $persistedFaceId = $this->azureFaceClient->addFaceToPerson($personId, $imageBase64);

            if (!$persistedFaceId) {
                return [
                    'success' => false,
                    'error' => 'Failed to add face to profile.'
                ];
            }

            // Train the person group
            $this->azureFaceClient->trainPersonGroup();

            // Save face person ID to user
            $user->setFacePersonId($personId);
            $user->setFaceEnabled(1);
            $this->entityManager->flush();

            $this->logger->info('Face enrolled successfully', [
                'userId' => $user->getId(),
                'personId' => $personId
            ]);

            return [
                'success' => true,
                'personId' => $personId,
                'message' => 'Face enrolled successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Face enrollment failed', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Face enrollment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Login user by face recognition
     */
    public function loginByFace(string $imageBase64): ?User
    {
        try {
            $this->logger->info('Starting face login process');

            // Detect face in the image
            $faceId = $this->azureFaceClient->detectFace($imageBase64);

            if (!$faceId) {
                $this->logger->warning('No face detected in login attempt');
                return null;
            }

            $this->logger->info('Face detected successfully', ['faceId' => $faceId]);

            // Identify the face against the person group
            $identification = $this->azureFaceClient->identifyFace($faceId);

            if (!$identification) {
                $this->logger->warning('No matching face found in person group');
                return null;
            }

            $personId = $identification['personId'];
            $confidence = $identification['confidence'];

            $this->logger->info('Face identified', [
                'personId' => $personId,
                'confidence' => $confidence,
                'threshold' => $this->azureFaceClient->config()->getConfidenceThreshold()
            ]);

            // Check confidence threshold
            if ($confidence < $this->azureFaceClient->config()->getConfidenceThreshold()) {
                $this->logger->warning('Confidence too low', [
                    'confidence' => $confidence,
                    'threshold' => $this->azureFaceClient->config()->getConfidenceThreshold()
                ]);
                return null;
            }

            // Find user by face person ID
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['facePersonId' => $personId, 'faceEnabled' => 1]);

            if (!$user) {
                $this->logger->warning('User not found for face ID', ['personId' => $personId]);
                return null;
            }

            $this->logger->info('User found successfully', [
                'userId' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            // Update last login time
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return $user;

        } catch (\Exception $e) {
            $this->logger->error('Face login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Delete user's face profile
     */
    public function deleteFace(User $user): bool
    {
        if (!$user->getFacePersonId()) {
            return true;
        }

        try {
            $success = $this->azureFaceClient->deletePerson($user->getFacePersonId());

            if ($success) {
                $user->setFacePersonId(null);
                $user->setFaceEnabled(0);
                $this->entityManager->flush();

                $this->logger->info('Face profile deleted', ['userId' => $user->getId()]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Face deletion failed', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has face enrolled
     */
    public function hasFaceEnrolled(User $user): bool
    {
        return $user->getFaceEnabled() === 1 && !empty($user->getFacePersonId());
    }
}