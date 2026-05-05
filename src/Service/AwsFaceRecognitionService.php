<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Aws\Rekognition\Exception\RekognitionException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AwsFaceRecognitionService
{
    public function __construct(
        private readonly AwsRekognitionConfigService $awsConfig,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        // Ensure collection exists on service initialization
        try {
            $this->awsConfig->ensureCollectionExists();
            $this->logger->info('AWS Rekognition collection ready', [
                'collection_id' => $this->awsConfig->getCollectionId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create/verify collection', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @return array{success: bool, faceId?: string, message?: string, error?: string}
     */
    public function enrollFace(User $user, string $imageBase64): array
    {
        try {
            $this->logger->info('AWS enrollFace started', [
                'userId' => $user->getId(),
                'userEmail' => $user->getEmail()
            ]);

            // Remove data URL prefix
            $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64) ?? '';
            $imageBytes = base64_decode($imageBase64);

            // Check if user already has a face enrolled
            if (!empty($user->getFacePersonId())) {
                $this->logger->info('User already has face enrolled, deleting old one first', [
                    'oldFaceId' => $user->getFacePersonId()
                ]);

                // Delete old face from collection
                try {
                    $rekognition = $this->awsConfig->getRekognitionClient();
                    $rekognition->deleteFaces([
                        'CollectionId' => $this->awsConfig->getCollectionId(),
                        'FaceIds' => [$user->getFacePersonId()]
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Could not delete old face', ['error' => $e->getMessage()]);
                }
            }

            $rekognition = $this->awsConfig->getRekognitionClient();

            // Detect face first
            $detectResult = $rekognition->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);

            if (empty($detectResult['FaceDetails'])) {
                return [
                    'success' => false,
                    'error' => 'No face detected. Please ensure good lighting.'
                ];
            }

            $this->logger->info('Face detected', [
                'confidence' => $detectResult['FaceDetails'][0]['Confidence']
            ]);

            // Index the face with unique ExternalImageId
            $result = $rekognition->indexFaces([
                'CollectionId' => $this->awsConfig->getCollectionId(),
                'Image' => ['Bytes' => $imageBytes],
                'MaxFaces' => 1,
                'QualityFilter' => 'AUTO',
                'ExternalImageId' => (string) $user->getId() . '_' . time(), // Make it unique
            ]);

            if (empty($result['FaceRecords'])) {
                $errorMsg = 'Could not index face';
                if (!empty($result['UnindexedFaces'])) {
                    $errorMsg .= ': ' . implode(', ', $result['UnindexedFaces'][0]['Reasons'] ?? ['unknown']);
                }
                return ['success' => false, 'error' => $errorMsg];
            }

            $faceId = $result['FaceRecords'][0]['Face']['FaceId'];

            $this->logger->info('Face indexed successfully', [
                'faceId' => $faceId,
                'userId' => $user->getId()
            ]);

            // Save to database
            $user->setFacePersonId($faceId);
            $user->setFaceEnabled(1);
            $this->entityManager->flush();

            return [
                'success' => true,
                'faceId' => $faceId,
                'message' => 'Face enrolled successfully'
            ];

        } catch (\Exception $e) {
            $this->logger->error('AWS enrollFace exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function loginByFace(string $imageBase64): ?User
    {
        try {
            $this->logger->info('Starting AWS face login process');

            $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64) ?? '';
            $imageBytes = base64_decode($imageBase64);

            $rekognition = $this->awsConfig->getRekognitionClient();

            // First, detect if there's a face
            $detectResult = $rekognition->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);

            if (empty($detectResult['FaceDetails'])) {
                $this->logger->warning('No face detected in login image');
                return null;
            }

            $this->logger->info('Face detected in login', [
                'confidence' => $detectResult['FaceDetails'][0]['Confidence']
            ]);

            // Search for face in collection
            $result = $rekognition->searchFacesByImage([
                'CollectionId' => $this->awsConfig->getCollectionId(),
                'Image' => ['Bytes' => $imageBytes],
                'FaceMatchThreshold' => $this->awsConfig->getFaceMatchThreshold(),
                'MaxFaces' => 1,
            ]);

            if (empty($result['FaceMatches'])) {
                $this->logger->warning('No face match found in AWS collection');
                return null;
            }

            $match = $result['FaceMatches'][0];
            $faceId = $match['Face']['FaceId'];
            $similarity = $match['Similarity'];

            $this->logger->info('Face matched in AWS', [
                'faceId' => $faceId,
                'similarity' => $similarity
            ]);

            // Find user by face ID
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['facePersonId' => $faceId]);

            if (!$user) {
                $this->logger->warning('User not found for face ID', ['faceId' => $faceId]);
                return null;
            }

            if ($user->getFaceEnabled() != 1) {
                $this->logger->warning('Face login disabled for user', ['userId' => $user->getId()]);
                return null;
            }

            $this->logger->info('User found for face', [
                'userId' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return $user;

        } catch (\Exception $e) {
            $this->logger->error('AWS face login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function deleteFace(User $user): bool
    {
        if (!$user->getFacePersonId()) {
            return true;
        }

        try {
            $rekognition = $this->awsConfig->getRekognitionClient();
            $rekognition->deleteFaces([
                'CollectionId' => $this->awsConfig->getCollectionId(),
                'FaceIds' => [$user->getFacePersonId()],
            ]);

            $user->setFacePersonId(null);
            $user->setFaceEnabled(0);
            $this->entityManager->flush();

            $this->logger->info('Face deleted from AWS', ['userId' => $user->getId()]);
            return true;

        } catch (RekognitionException $e) {
            $this->logger->error('Face deletion from AWS failed', [
                'userId' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function hasFaceEnrolled(User $user): bool
    {
        return $user->getFaceEnabled() === 1 && !empty($user->getFacePersonId());
    }
}