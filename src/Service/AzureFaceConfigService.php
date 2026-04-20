<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

class AzureFaceConfigService
{
    private string $endpoint;
    private string $key;
    private string $personGroupId;
    private string $recognitionModel;
    private float $confidenceThreshold;
    private string $detectionModel;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        // Load from environment variables
        $this->endpoint = rtrim($_ENV['AZURE_FACE_ENDPOINT'] ?? getenv('AZURE_FACE_ENDPOINT') ?: '', '/');
        $this->key = $_ENV['AZURE_FACE_KEY'] ?? getenv('AZURE_FACE_KEY') ?: '';
        $this->personGroupId = $_ENV['AZURE_FACE_PERSON_GROUP_ID'] ?? getenv('AZURE_FACE_PERSON_GROUP_ID') ?: 'carrieri-users';
        $this->recognitionModel = $_ENV['AZURE_FACE_RECOGNITION_MODEL'] ?? getenv('AZURE_FACE_RECOGNITION_MODEL') ?: 'recognition_04';
        $this->confidenceThreshold = floatval($_ENV['AZURE_FACE_CONFIDENCE_THRESHOLD'] ?? getenv('AZURE_FACE_CONFIDENCE_THRESHOLD') ?: '0.6');
        $this->detectionModel = $_ENV['AZURE_FACE_DETECTION_MODEL'] ?? getenv('AZURE_FACE_DETECTION_MODEL') ?: 'detection_03';

        // Debug: Log credentials (remove in production)
        $this->logger->info('Azure Face Config loaded', [
            'endpoint' => $this->endpoint,
            'key_prefix' => substr($this->key, 0, 10) . '...',
            'key_length' => strlen($this->key),
            'person_group_id' => $this->personGroupId
        ]);

        // Validate credentials
        if (empty($this->endpoint)) {
            $this->logger->error('AZURE_FACE_ENDPOINT is not set in .env file');
        }
        if (empty($this->key)) {
            $this->logger->error('AZURE_FACE_KEY is not set in .env file');
        }
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getPersonGroupId(): string
    {
        return $this->personGroupId;
    }

    public function getRecognitionModel(): string
    {
        return $this->recognitionModel;
    }

    public function getConfidenceThreshold(): float
    {
        return $this->confidenceThreshold;
    }

    public function getDetectionModel(): string
    {
        return $this->detectionModel;
    }

    public function getApiKey(): string
    {
        return $this->key;
    }

    public function getBaseUrl(): string
    {
        return $this->endpoint . '/face/v1.0';
    }
}