<?php
// src/Service/AiCodeEvaluatorService.php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Calls the Python AI evaluation micro-service and returns a normalised array:
 *
 *   [
 *     'score'        => float,           // 0–100
 *     'statut'       => string,          // 'accepte' | 'refuse'
 *     'feedback'     => string,          // markdown text
 *     'resultat_html'=> string,          // HTML stored in rendu_mission.resultat
 *     'summary'      => string,
 *   ]
 */
class AiCodeEvaluatorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $aiApiBaseUrl = 'http://127.0.0.1:8001',
    ) {}

    /**
     * @return array{score: float, statut: string, feedback: string, resultat_html: string, summary: string}
     *
     * @throws \RuntimeException when the AI service is unavailable
     */
    public function evaluate(
        string $code,
        string $language,
        string $missionDescription,
        string $missionTitle = '',
        int    $scoreMin = 60,
    ): array {
        $payload = [
            'code'                => $code,
            'language'            => $language,
            'mission_description' => $missionDescription,
            'mission_title'       => $missionTitle,
            'score_min'           => $scoreMin,
        ];

        try {
            $this->logger->info('Calling AI evaluation API', [
                'url' => $this->aiApiBaseUrl . '/evaluate',
                'language' => $language,
                'mission' => $missionTitle
            ]);

            $response = $this->httpClient->request('POST', $this->aiApiBaseUrl . '/evaluate', [
                'json'    => $payload,
                'timeout' => 60,
            ]);

            $data = $response->toArray();

            $this->logger->info('AI evaluation successful', [
                'score' => $data['score'] ?? null,
                'statut' => $data['statut'] ?? null
            ]);

            return [
                'score'         => (float)  ($data['score']        ?? 0),
                'statut'        => (string) ($data['statut']       ?? 'refuse'),
                'feedback'      => (string) ($data['feedback']     ?? ''),
                'resultat_html' => (string) ($data['resultat_html'] ?? ''),
                'summary'       => (string) ($data['summary']      ?? ''),
            ];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('AiCodeEvaluatorService: transport error – {msg}', ['msg' => $e->getMessage()]);
            throw new \RuntimeException('Le service d\'évaluation IA est inaccessible. Veuillez réessayer.', 0, $e);

        } catch (\Throwable $e) {
            $this->logger->error('AiCodeEvaluatorService: unexpected error – {msg}', ['msg' => $e->getMessage()]);
            throw new \RuntimeException('Erreur lors de l\'évaluation IA : ' . $e->getMessage(), 0, $e);
        }
    }
}
