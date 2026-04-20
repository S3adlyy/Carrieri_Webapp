<?php

declare(strict_types=1);

namespace App\Service;

use Aws\Rekognition\RekognitionClient;

class AwsRekognitionConfigService
{
    private string $region;
    private string $collectionId;
    private float $faceMatchThreshold;
    private ?RekognitionClient $rekognitionClient = null;

    public function __construct()
    {
        $this->region = $_ENV['AWS_REGION'] ?? 'eu-west-1';
        $this->collectionId = $_ENV['AWS_REKOGNITION_COLLECTION_ID'] ?? 'Carrieri-Web';
        $this->faceMatchThreshold = floatval($_ENV['AWS_FACE_MATCH_THRESHOLD'] ?? '90');
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
        if ($this->rekognitionClient === null) {
            $this->rekognitionClient = new RekognitionClient([
                'version' => 'latest',
                'region' => $this->region,
            ]);
        }
        return $this->rekognitionClient;
    }

    public function ensureCollectionExists(): void
    {
        try {
            $this->getRekognitionClient()->createCollection([
                'CollectionId' => $this->collectionId,
            ]);
        } catch (\Exception $e) {
            // Collection already exists - ignore
            $errorMsg = $e->getMessage();
            if (!str_contains($errorMsg, 'ResourceAlreadyExistsException') &&
                !str_contains($errorMsg, 'already exists')) {
                throw $e;
            }
        }
    }
}