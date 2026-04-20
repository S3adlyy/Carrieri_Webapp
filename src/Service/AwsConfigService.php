<?php

declare(strict_types=1);

namespace App\Service;

use Aws\Rekognition\RekognitionClient;

class AwsConfigService
{
    private string $region;
    private string $collectionId;
    private float $faceMatchThreshold;
    private RekognitionClient $rekognitionClient;

    public function __construct()
    {
        // Load from environment variables
        $this->region = $_ENV['AWS_REGION'] ?? 'us-east-1';
        $this->collectionId = $_ENV['AWS_REKOGNITION_COLLECTION_ID'] ?? 'carrieri_faces';
        $this->faceMatchThreshold = floatval($_ENV['AWS_FACE_MATCH_THRESHOLD'] ?? '90');

        // Initialize Rekognition client
        $this->rekognitionClient = new RekognitionClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            ],
        ]);
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }

    public function getFaceMatchThreshold(): float
    {
        return $this->faceMatchThreshold;
    }

    public function getRekognitionClient(): RekognitionClient
    {
        return $this->rekognitionClient;
    }

    public function ensureCollectionExists(): void
    {
        try {
            // Try to create collection (will throw if exists)
            $this->rekognitionClient->createCollection([
                'CollectionId' => $this->collectionId,
            ]);
        } catch (\Exception $e) {
            // Collection already exists - ignore
            if (!str_contains($e->getMessage(), 'ResourceAlreadyExistsException')) {
                throw $e;
            }
        }
    }
}