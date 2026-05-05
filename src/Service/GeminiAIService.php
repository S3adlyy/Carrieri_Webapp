<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiAIService
{
    private string $apiKey;
    private string $model = 'gemini-3-flash-preview'; // or gemini-2.0-flash-exp

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
        $this->apiKey = 'AIzaSyDBRWP3lzdPFBE7TmEk1Z61wGBUfo7AIGo';
    }

    /**
     * Improve the user's bio/about section using AI
     * @param array<string, mixed> $userData
     */
    public function improveBio(string $currentBio, array $userData = []): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Please add GEMINI_API_KEY to your .env file.');
        }

        // Build a rich prompt with user data
        $prompt = $this->buildBioImprovementPrompt($currentBio, $userData);

        return $this->callGeminiApi($prompt);
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function buildBioImprovementPrompt(string $currentBio, array $userData): string
    {
        $contextInfo = '';

        if (!empty($userData)) {
            $contextInfo = "\n\nCONTEXT INFORMATION ABOUT THE USER:\n";

            if (!empty($userData['firstName']) || !empty($userData['lastName'])) {
                $contextInfo .= "- Name: " . ($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? '') . "\n";
            }

            if (!empty($userData['headline'])) {
                $contextInfo .= "- Professional Headline: " . $userData['headline'] . "\n";
            }

            if (!empty($userData['hardSkills'])) {
                $contextInfo .= "- Hard Skills: " . $userData['hardSkills'] . "\n";
            }

            if (!empty($userData['softSkills'])) {
                $contextInfo .= "- Soft Skills: " . $userData['softSkills'] . "\n";
            }

            if (!empty($userData['school']) && !empty($userData['degree'])) {
                $contextInfo .= "- Education: " . $userData['degree'] . " from " . $userData['school'] . "\n";
            }

            if (!empty($userData['orgName'])) {
                $contextInfo .= "- Organization: " . $userData['orgName'] . "\n";
            }

            if (!empty($userData['type'])) {
                $contextInfo .= "- User Type: " . $userData['type'] . "\n";
            }
        }

        return "Improve this professional 'About' section / biography.\n\n" .
            "RULES:\n" .
            "- Keep the same language as the input (French or English)\n" .
            "- Keep it truthful; do NOT invent facts that aren't provided\n" .
            "- Use the context information to enrich the bio naturally\n" .
            "- Write 2 to 4 short, engaging paragraphs\n" .
            "- Make it professional, compelling, and authentic\n" .
            "- Output ONLY the improved bio text, no explanations\n" .
            "- Do not use markdown or special formatting\n\n" .
            "CURRENT BIO:\n" . $currentBio .
            $contextInfo . "\n\n" .
            "IMPROVED BIO:";
    }

    private function callGeminiApi(string $prompt): string
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->apiKey
        );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode !== 200) {
                $this->logger->error('Gemini API error', [
                    'status' => $statusCode,
                    'response' => $content
                ]);
                throw new \RuntimeException('Gemini API returned error: ' . ($content['error']['message'] ?? 'Unknown error'));
            }

            // Extract the generated text
            $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($text)) {
                throw new \RuntimeException('Empty response from Gemini API');
            }

            return trim($text);

        } catch (\Exception $e) {
            $this->logger->error('Gemini API call failed', [
                'error' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 500)
            ]);
            throw new \RuntimeException('Failed to improve bio: ' . $e->getMessage());
        }
    }
}