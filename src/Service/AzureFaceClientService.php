<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class AzureFaceClientService
{
    private Client $httpClient;
    private Client $binaryClient;
    private AzureFaceConfigService $config;
    private LoggerInterface $logger;

    public function __construct(AzureFaceConfigService $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        // Client for JSON requests
        $this->httpClient = new Client([
            'timeout' => 60,
            'connect_timeout' => 30,
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->config->getApiKey(),
                'Content-Type' => 'application/json',
            ],
        ]);

        // Client for binary/image requests
        $this->binaryClient = new Client([
            'timeout' => 60,
            'connect_timeout' => 30,
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->config->getApiKey(),
                'Content-Type' => 'application/octet-stream',
            ],
        ]);
    }

    /**
     * Create Person Group if it doesn't exist
     */
    public function createPersonGroupIfNotExists(): bool
    {
        try {
            $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId();

            $response = $this->httpClient->put($url, [
                'json' => [
                    'name' => $this->config->getPersonGroupId(),
                    'recognitionModel' => $this->config->getRecognitionModel(),
                ],
            ]);

            $this->logger->info('Person group created successfully', [
                'groupId' => $this->config->getPersonGroupId()
            ]);
            return true;

        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;

            // Person group already exists
            if ($statusCode === 409) {
                $this->logger->info('Person group already exists', [
                    'groupId' => $this->config->getPersonGroupId()
                ]);
                return true;
            }

            $this->logger->error('Failed to create person group', [
                'error' => $e->getMessage(),
                'statusCode' => $statusCode
            ]);
            return false;
        }
    }

    /**
     * Create a person in the person group
     */
    public function createPerson(int $userId, string $name): ?string
    {
        try {
            $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId() . '/persons';

            $response = $this->httpClient->post($url, [
                'json' => [
                    'name' => $name,
                    'userData' => (string) $userId,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $personId = $data['personId'] ?? null;

            $this->logger->info('Person created', [
                'userId' => $userId,
                'personId' => $personId
            ]);

            return $personId;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to create person', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Add face to a person
     */
    public function addFaceToPerson(string $personId, string $imageBase64): ?string
    {
        try {
            // Remove data:image/jpeg;base64, prefix if present
            $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);

            $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId()
                . '/persons/' . $personId . '/persistedFaces'
                . '?detectionModel=' . $this->config->getDetectionModel();

            // Use a separate client for binary data
            $binaryClient = new Client([
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->config->getApiKey(),
                    'Content-Type' => 'application/octet-stream',
                ],
            ]);

            $response = $binaryClient->post($url, [
                'body' => base64_decode($imageBase64),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $persistedFaceId = $data['persistedFaceId'] ?? null;

            $this->logger->info('Face added to person', [
                'personId' => $personId,
                'persistedFaceId' => $persistedFaceId
            ]);

            return $persistedFaceId;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to add face to person', [
                'personId' => $personId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Train person group
     */
    public function trainPersonGroup(): bool
    {
        try {
            $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId() . '/train';

            $this->httpClient->post($url);

            // Wait for training to complete
            $this->waitForTrainingCompletion();

            $this->logger->info('Person group trained successfully');
            return true;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to train person group', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Wait for training to complete
     */
    private function waitForTrainingCompletion(int $maxAttempts = 30): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId() . '/training';
                $response = $this->httpClient->get($url);
                $data = json_decode($response->getBody()->getContents(), true);

                $status = $data['status'] ?? '';
                if ($status === 'succeeded') {
                    return;
                } elseif ($status === 'failed') {
                    throw new \Exception('Training failed');
                }

                sleep(1);
            } catch (\Exception $e) {
                // Continue waiting
            }
        }

        throw new \Exception('Training timeout');
    }

    /**
     * Detect face and get face ID
     */
    public function detectFace(string $imageBase64): ?string
    {
        try {
            // Handle both data URL and raw base64
            if (str_contains($imageBase64, 'base64,')) {
                $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
            }

            $imageBytes = base64_decode($imageBase64);

            // Log image info
            $this->logger->info('Detecting face', [
                'base64_length' => strlen($imageBase64),
                'bytes_length' => strlen($imageBytes),
                'size_kb' => strlen($imageBytes) / 1024
            ]);

            // Check minimum size
            if (strlen($imageBytes) < 5000) { // Less than 5KB
                $this->logger->warning('Image too small', ['size' => strlen($imageBytes)]);
                return null;
            }

            $url = $this->config->getBaseUrl() . '/detect'
                . '?returnFaceId=true'
                . '&returnFaceLandmarks=false'
                . '&recognitionModel=' . $this->config->getRecognitionModel()
                . '&detectionModel=' . $this->config->getDetectionModel();

            // Use curl directly for better debugging
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Ocp-Apim-Subscription-Key: ' . $this->config->getKey(),
                'Content-Type: application/octet-stream'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $imageBytes);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $this->logger->info('Azure detect response', [
                'http_code' => $httpCode,
                'response_length' => strlen($response)
            ]);

            if ($httpCode !== 200) {
                $this->logger->error('Face detection API error', [
                    'http_code' => $httpCode,
                    'error' => $curlError ?: $response
                ]);
                return null;
            }

            $data = json_decode($response, true);

            if (empty($data)) {
                $this->logger->warning('No faces detected in image');
                return null;
            }

            $faceId = $data[0]['faceId'] ?? null;
            $this->logger->info('Face detected', ['faceId' => $faceId]);

            return $faceId;

        } catch (\Exception $e) {
            $this->logger->error('Face detection exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Identify face against person group
     */
    public function identifyFace(string $faceId): ?array
    {
        try {
            $this->logger->info('Starting face identification', ['faceId' => $faceId]);

            $url = $this->config->getBaseUrl() . '/identify';

            $response = $this->httpClient->post($url, [
                'json' => [
                    'personGroupId' => $this->config->getPersonGroupId(),
                    'faceIds' => [$faceId],
                    'maxNumOfCandidatesReturned' => 1,
                    'confidenceThreshold' => $this->config->getConfidenceThreshold(),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            $this->logger->info('Identify API response', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            $data = json_decode($responseBody, true);

            if (empty($data) || empty($data[0]['candidates'])) {
                $this->logger->warning('No identification candidates found');
                return null;
            }

            $candidate = $data[0]['candidates'][0];

            $this->logger->info('Face identified', [
                'personId' => $candidate['personId'],
                'confidence' => $candidate['confidence']
            ]);

            return [
                'personId' => $candidate['personId'],
                'confidence' => $candidate['confidence'],
            ];

        } catch (GuzzleException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $responseBody = $response ? $response->getBody()->getContents() : '';

            $this->logger->error('Face identification failed', [
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Face identification exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete person from group
     */
    public function deletePerson(string $personId): bool
    {
        try {
            $url = $this->config->getBaseUrl() . '/persongroups/' . $this->config->getPersonGroupId() . '/persons/' . $personId;

            $this->httpClient->delete($url);

            $this->logger->info('Person deleted', ['personId' => $personId]);
            return true;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to delete person', [
                'personId' => $personId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}