<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OffreEmploi;
use App\Entity\User;

class CandidateOfferMatchService
{
    private const SYNONYM_GROUPS = [
        'developpeur' => ['dev', 'developpeur', 'developpeuse', 'developer'],
        'javascript' => ['js', 'javascript'],
        'typescript' => ['ts', 'typescript'],
        'symfony' => ['symfony', 'php-symfony'],
        'java' => ['java', 'j2ee', 'spring'],
        'frontend' => ['frontend', 'front', 'front-end'],
        'backend' => ['backend', 'back', 'back-end'],
        'fullstack' => ['fullstack', 'full-stack', 'full'],
        'intelligence_artificielle' => ['ai', 'ia'],
        'base_de_donnees' => ['sql', 'mysql', 'postgresql', 'mariadb', 'database'],
        'gestion_projet' => ['scrum', 'agile'],
    ];

    public function match(User $candidate, OffreEmploi $offre): array
    {
        $offerTitleKeywords = $this->extractKeywords((string) $offre->getTitre(), 2, 8);
        $offerCompetenceKeywords = $this->extractKeywords((string) $offre->getCompetencesRequises(), 2, 12);
        $offerDescriptionKeywords = $this->extractKeywords((string) $offre->getDescription(), 4, 8);

        $candidatePrimaryKeywords = $this->extractKeywords(
            implode(' ', array_filter([
                $candidate->getHardSkills(),
                $candidate->getHeadline(),
                $candidate->getFieldOfStudy(),
                $candidate->getDegree(),
            ])),
            2,
            18
        );
        $candidateSecondaryKeywords = $this->extractKeywords(
            implode(' ', array_filter([
                $candidate->getSoftSkills(),
                $candidate->getBio(),
            ])),
            3,
            20
        );
        $candidateKeywords = array_values(array_unique(array_merge(
            $candidatePrimaryKeywords,
            $candidateSecondaryKeywords
        )));

        $matchedTitleKeywords = array_values(array_intersect($offerTitleKeywords, $candidateKeywords));
        $matchedCompetenceKeywords = array_values(array_intersect($offerCompetenceKeywords, $candidateKeywords));
        $matchedDescriptionKeywords = array_values(array_intersect($offerDescriptionKeywords, $candidateKeywords));

        $titleScore = $this->calculateRatioScore($offerTitleKeywords, $matchedTitleKeywords, 55);
        $skillsScore = $this->calculateRatioScore($offerCompetenceKeywords, $matchedCompetenceKeywords, 50);
        $descriptionScore = $this->calculateDescriptionScore($matchedDescriptionKeywords);
        $qualificationScore = $this->calculateQualificationScore($candidate, $offre);
        $locationScore = $this->calculateLocationScore($candidate, $offre);
        $experienceScore = $this->calculateExperienceScore($candidate, $offre);

        $missingSkillSource = $offerCompetenceKeywords !== []
            ? $offerCompetenceKeywords
            : $this->filterMeaningfulTitleKeywords($offerTitleKeywords);

        $missingSkills = array_values(array_diff(
            $missingSkillSource,
            $candidateKeywords
        ));

        $globalScore = (int) round(
            ($titleScore * 0.30) +
            ($skillsScore * 0.30) +
            ($descriptionScore * 0.15) +
            ($qualificationScore * 0.10) +
            ($locationScore * 0.15) +
            ($experienceScore * 0.10)
        );

        $reasons = $this->buildReasons(
            $matchedTitleKeywords,
            $matchedCompetenceKeywords,
            $matchedDescriptionKeywords,
            $titleScore,
            $skillsScore,
            $descriptionScore,
            $qualificationScore,
            $locationScore,
            $experienceScore
        );

        return [
            'score' => $this->clamp($globalScore),
            'label' => $this->buildLabel($globalScore),
            'tone' => $this->buildTone($globalScore),
            'reasons' => $reasons,
            'missing_skills' => array_slice($missingSkills, 0, 5),
            'breakdown' => [
                'title' => $titleScore,
                'skills' => $skillsScore,
                'description' => $descriptionScore,
                'qualification' => $qualificationScore,
                'location' => $locationScore,
                'experience' => $experienceScore,
            ],
        ];
    }

    private function calculateRatioScore(array $sourceKeywords, array $matchedKeywords, int $fallback): int
    {
        if ($sourceKeywords === []) {
            return $fallback;
        }

        $ratio = count($matchedKeywords) / count($sourceKeywords);

        return $this->clamp((int) round($ratio * 100));
    }

    private function calculateDescriptionScore(array $matchedDescriptionKeywords): int
    {
        $matchesCount = count($matchedDescriptionKeywords);

        return match (true) {
            $matchesCount >= 3 => 90,
            $matchesCount === 2 => 75,
            $matchesCount === 1 => 60,
            default => 45,
        };
    }

    private function calculateQualificationScore(User $candidate, OffreEmploi $offre): int
    {
        $candidateText = $this->normalizeText(implode(' ', array_filter([
            $candidate->getNiveau(),
            $candidate->getDegree(),
            $candidate->getFieldOfStudy(),
            $candidate->getBio(),
        ])));
        $offerText = $this->normalizeText((string) $offre->getNiveauQualification());

        if ($offerText === '') {
            return 60;
        }

        if ($candidateText !== '' && str_contains($candidateText, $offerText)) {
            return 95;
        }

        $candidateKeywords = $this->extractKeywords($candidateText);
        $offerKeywords = $this->extractKeywords($offerText);

        if ($offerKeywords === []) {
            return 60;
        }

        $matches = array_intersect($candidateKeywords, $offerKeywords);

        if ($matches !== []) {
            return 75;
        }

        return 35;
    }

    private function calculateLocationScore(User $candidate, OffreEmploi $offre): int
    {
        $candidateLocation = $this->normalizeText((string) $candidate->getLocation());
        $offerLocation = $this->normalizeText((string) $offre->getLocalisation());

        if ($candidateLocation === '' || $offerLocation === '') {
            return 55;
        }

        if ($candidateLocation === $offerLocation) {
            return 100;
        }

        if (
            str_contains($candidateLocation, $offerLocation) ||
            str_contains($offerLocation, $candidateLocation)
        ) {
            return 80;
        }

        return 30;
    }

    private function calculateExperienceScore(User $candidate, OffreEmploi $offre): int
    {
        $candidateText = $this->normalizeText(implode(' ', array_filter([
            $candidate->getHeadline(),
            $candidate->getBio(),
        ])));
        $offerText = $this->normalizeText((string) $offre->getExperienceRequise());

        if ($offerText === '') {
            return 60;
        }

        if ($candidateText === '') {
            return 40;
        }

        if (preg_match('/\d+/', $offerText, $offerYears) === 1) {
            if (preg_match('/\d+/', $candidateText, $candidateYears) === 1) {
                return ((int) $candidateYears[0] >= (int) $offerYears[0]) ? 90 : 50;
            }
        }

        $offerKeywords = $this->extractKeywords($offerText);
        $candidateKeywords = $this->extractKeywords($candidateText);

        if (array_intersect($offerKeywords, $candidateKeywords) !== []) {
            return 75;
        }

        return 45;
    }

    private function buildReasons(
        array $matchedTitleKeywords,
        array $matchedCompetenceKeywords,
        array $matchedDescriptionKeywords,
        int $titleScore,
        int $skillsScore,
        int $descriptionScore,
        int $qualificationScore,
        int $locationScore,
        int $experienceScore
    ): array {
        $reasons = [];

        if ($matchedTitleKeywords !== []) {
            $reasons[] = 'Titre aligned: ' . implode(', ', array_slice($matchedTitleKeywords, 0, 3));
        }

        if ($matchedCompetenceKeywords !== []) {
            $reasons[] = 'Skills aligned: ' . implode(', ', array_slice($matchedCompetenceKeywords, 0, 3));
        }

        if ($titleScore >= 70) {
            $reasons[] = 'Le titre du poste correspond bien au profil.';
        }

        if ($descriptionScore >= 75 && $matchedDescriptionKeywords !== []) {
            $reasons[] = 'Description aligned: ' . implode(', ', array_slice($matchedDescriptionKeywords, 0, 3));
        }

        if ($qualificationScore >= 75) {
            $reasons[] = 'Qualification compatible avec l\'offre.';
        }

        if ($locationScore >= 80) {
            $reasons[] = 'Bonne compatibilite de localisation.';
        }

        if ($experienceScore >= 75) {
            $reasons[] = 'Experience du profil coherente avec le poste.';
        }

        if ($skillsScore >= 70 && $matchedCompetenceKeywords === []) {
            $reasons[] = 'Bon alignement technique global.';
        }

        if ($reasons === []) {
            $reasons[] = 'Compatibilite partielle detectee a partir du profil actuel.';
        }

        return array_slice(array_values(array_unique($reasons)), 0, 4);
    }

    private function buildLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excellent match',
            $score >= 65 => 'Good match',
            $score >= 50 => 'Medium match',
            default => 'Low match',
        };
    }

    private function buildTone(int $score): string
    {
        return match (true) {
            $score >= 70 => 'good',
            $score >= 50 => 'medium',
            default => 'low',
        };
    }

    private function extractKeywords(string $text, int $minLength = 3, int $limit = 15): array
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9+#]+/', $normalized) ?: [];
        $parts = array_filter($parts, function (string $part): bool {
            return $part !== '';
        });
        $parts = array_values($parts);

        $keywords = [];
        foreach ($parts as $part) {
            $part = $this->normalizeKeyword($part);

            if (mb_strlen($part) < $minLength || $this->isStopWord($part)) {
                continue;
            }

            if (!in_array($part, $keywords, true)) {
                $keywords[] = $part;
            }

            if (count($keywords) >= $limit) {
                break;
            }
        }

        return $keywords;
    }

    private function normalizeKeyword(string $keyword): string
    {
        foreach (self::SYNONYM_GROUPS as $canonical => $variants) {
            if (in_array($keyword, $variants, true)) {
                return $canonical;
            }
        }

        return $keyword;
    }

    private function filterMeaningfulTitleKeywords(array $keywords): array
    {
        return array_values(array_filter($keywords, function (string $keyword): bool {
            return !$this->isRoleWord($keyword);
        }));
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $converted = $converted !== false ? $converted : $text;

        return mb_strtolower($converted);
    }

    private function isStopWord(string $word): bool
    {
        static $stopWords = [
            'avec', 'dans', 'pour', 'sans', 'plus', 'moins', 'tres', 'trop',
            'une', 'des', 'les', 'aux', 'sur', 'par', 'and', 'the', 'job',
            'poste', 'profil', 'recherche', 'required', 'experience', 'ans',
            'year', 'years', 'from', 'that', 'this', 'vous', 'nous', 'leur',
            'elle', 'ils', 'ses', 'son', 'est', 'offre', 'emploi', 'cdi',
            'cdd', 'stage', 'rejoindre', 'developpeur', 'developpeuse',
            'senior', 'junior', 'entreprise', 'mission', 'missions', 'projet',
            'projets', 'poste', 'travail', 'equipe', 'equipes', 'contexte',
            'besoins', 'concret', 'concrets', 'maintenance', 'evoluer',
            'evolution', 'test', 'marketinggg', 'currently', 'looking',
            'join', 'bac', 'niveau', 'avoir', 'faire', 'sera', 'cette',
        ];

        return in_array($word, $stopWords, true);
    }

    private function isRoleWord(string $word): bool
    {
        static $roleWords = [
            'developpeur', 'developpeuse', 'developer', 'ingenieur', 'consultant',
            'consultante', 'manager', 'responsable', 'specialiste', 'analyste',
            'chef', 'senior', 'junior', 'confirme', 'confirmee', 'assistant',
            'stagiaire', 'stag', 'eveloppeur', 'veloppeur',
        ];

        return in_array($word, $roleWords, true);
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
