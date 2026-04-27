<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OffreEmploi;
use App\Entity\User;
use Symfony\Component\Process\Process;

class CandidateOfferAiScoringService
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function score(User $candidate, OffreEmploi $offre): ?array
    {
        $scriptPath = $this->projectDir . '/scripts/match_score.py';
        if (!is_file($scriptPath)) {
            return null;
        }

        $payload = [
            'candidate' => [
                'headline' => $candidate->getHeadline(),
                'hardSkills' => $candidate->getHardSkills(),
                'softSkills' => $candidate->getSoftSkills(),
                'bio' => $candidate->getBio(),
                'location' => $candidate->getLocation(),
                'niveau' => $candidate->getNiveau(),
                'degree' => $candidate->getDegree(),
                'fieldOfStudy' => $candidate->getFieldOfStudy(),
            ],
            'offer' => [
                'titre' => $offre->getTitre(),
                'competences' => $offre->getCompetencesRequises(),
                'description' => $offre->getDescription(),
                'typeContrat' => $offre->getTypeContrat(),
                'localisation' => $offre->getLocalisation(),
                'qualification' => $offre->getNiveauQualification(),
                'experience' => $offre->getExperienceRequise(),
            ],
        ];

        try {
            $process = new Process([
                'python',
                $scriptPath,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $process->setTimeout(20);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $decoded = json_decode(trim($process->getOutput()), true);
            if (!is_array($decoded)) {
                return null;
            }

            return [
                'score_global' => isset($decoded['score_global']) ? max(0, min(100, (int) $decoded['score_global'])) : null,
                'summary' => isset($decoded['summary']) ? (string) $decoded['summary'] : '',
                'strengths' => isset($decoded['strengths']) && is_array($decoded['strengths']) ? array_values($decoded['strengths']) : [],
                'missing' => isset($decoded['missing']) && is_array($decoded['missing']) ? array_values($decoded['missing']) : [],
                'focus' => isset($decoded['focus']) && is_array($decoded['focus']) ? $decoded['focus'] : [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
