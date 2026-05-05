<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OffreEmploi;
use App\Entity\Postulation;


class OffreInsightService
{
    /**
     * @param Postulation[] $postulations
     * @return array<string, mixed>
     */
    public function analyze(OffreEmploi $offre, array $postulations = []): array
    {
        $title = trim((string) $offre->getTitre());
        $description = trim((string) $offre->getDescription());
        $skills = trim((string) $offre->getCompetencesRequises());
        $location = trim((string) $offre->getLocalisation());
        $contract = trim((string) $offre->getTypeContrat());
        $qualification = trim((string) $offre->getNiveauQualification());
        $experience = trim((string) $offre->getExperienceRequise());
        $sector = trim((string) $offre->getSecteurActivite());
        $contact = trim((string) $offre->getContactRecruteur());
        $salary = $offre->getSalaire();

        $descriptionLength = mb_strlen($description);
        $titleLength = mb_strlen($title);
        $skillsLength = mb_strlen($skills);

        $totalPostulations = count($postulations);
        $accepted = 0;
        $refused = 0;
        $pending = 0;
        $withCv = 0;

        foreach ($postulations as $postulation) {
            if ($postulation->getCvPath()) {
                $withCv++;
            }

            match ($postulation->getStatut()) {
                'Acceptée' => $accepted++,
                'Refusée' => $refused++,
                default => $pending++,
            };
        }

        $clarity = 45;
        $attractiveness = 40;
        $transparency = 35;
        $competitiveness = 40;
        $credibility = 45;

        $remarks = [];
        $strengths = [];
        $weaknesses = [];

        // TITRE
        if ($titleLength >= 10 && $titleLength <= 60) {
            $clarity += 18;
            $strengths[] = "Le titre est assez clair et lisible.";
        } else {
            $remarks[] = "Le titre gagnerait à être plus précis et plus direct pour mieux capter l’attention des candidats.";
            $weaknesses[] = "Titre perfectible.";
        }

        if (
            stripos($title, 'urgent') !== false ||
            stripos($title, 'cherche') !== false ||
            stripos($title, 'recherche') !== false
        ) {
            $clarity -= 6;
            $credibility -= 4;
            $remarks[] = "Évite les formulations trop génériques comme “urgent” ou “cherche” dans le titre, et privilégie l’intitulé exact du poste.";
        }

        // DESCRIPTION
        if ($descriptionLength >= 350) {
            $clarity += 20;
            $credibility += 8;
            $strengths[] = "La description contient déjà un bon niveau de détail.";
        } elseif ($descriptionLength >= 180) {
            $clarity += 10;
            $credibility += 4;
            $remarks[] = "La description est correcte mais peut encore être enrichie avec plus de détails sur les missions et l’environnement de travail.";
        } else {
            $clarity -= 8;
            $credibility -= 6;
            $remarks[] = "La description est trop courte. Aujourd’hui, les candidats réagissent mieux aux offres détaillées et concrètes.";
            $weaknesses[] = "Description trop courte.";
        }

        // SALAIRE
        if (!empty($salary) && (float) $salary > 0) {
            $transparency += 28;
            $competitiveness += 10;
            $strengths[] = "La transparence salariale améliore clairement l’attractivité de l’offre.";
        } else {
            $transparency -= 10;
            $remarks[] = "Ajouter une fourchette salariale rend l’offre plus crédible et augmente souvent le taux de clic et de candidature.";
            $weaknesses[] = "Salaire non communiqué.";
        }

        // COMPÉTENCES
        if ($skillsLength >= 30) {
            $clarity += 8;
            $competitiveness += 10;
            $strengths[] = "Les compétences demandées sont mentionnées.";
        } else {
            $clarity -= 5;
            $remarks[] = "Précise davantage les compétences clés attendues afin d’attirer des profils mieux ciblés.";
            $weaknesses[] = "Compétences trop peu détaillées.";
        }

        // LOCALISATION / CONTRAT / QUALIF / EXPERIENCE / CONTACT
        if ($location !== '') {
            $transparency += 8;
        } else {
            $remarks[] = "Ajoute la localisation exacte ou au moins la ville pour réduire l’incertitude côté candidat.";
        }

        if ($contract !== '') {
            $transparency += 8;
        } else {
            $remarks[] = "Le type de contrat doit être clairement visible pour améliorer la lisibilité de l’offre.";
        }

        if ($qualification !== '') {
            $credibility += 6;
        } else {
            $remarks[] = "Ajoute le niveau de qualification recherché pour mieux cadrer le poste.";
        }

        if ($experience !== '') {
            $credibility += 6;
        } else {
            $remarks[] = "Précise l’expérience attendue pour filtrer naturellement les candidatures.";
        }

        if ($contact !== '') {
            $credibility += 10;
            $transparency += 5;
        } else {
            $remarks[] = "Afficher un contact ou un canal clair de communication renforce la confiance des candidats.";
        }

        // BONUS mots-clés “marché actuel”
        $descriptionLower = mb_strtolower($description);

        $marketSignals = [
            'télétravail', 'remote', 'hybride', 'flexible',
            'évolution', 'formation', 'avantages', 'culture',
            'équipe', 'impact', 'projet', 'innovation'
        ];

        $marketHits = 0;
        foreach ($marketSignals as $signal) {
            if (str_contains($descriptionLower, $signal)) {
                $marketHits++;
            }
        }

        if ($marketHits >= 3) {
            $attractiveness += 20;
            $competitiveness += 12;
            $strengths[] = "L’offre contient plusieurs signaux attractifs recherchés aujourd’hui par les candidats.";
        } elseif ($marketHits >= 1) {
            $attractiveness += 10;
            $remarks[] = "Tu peux encore mieux valoriser l’offre en mettant en avant les avantages, la flexibilité, la montée en compétences ou l’impact du poste.";
        } else {
            $attractiveness -= 8;
            $remarks[] = "Le marché valorise beaucoup plus qu’avant la flexibilité, les avantages, l’évolution, la culture d’équipe et l’impact du poste. Ajoute ces éléments si possible.";
            $weaknesses[] = "Peu de signaux d’attractivité modernes.";
        }

        // MOTS TROP GÉNÉRIQUES / TROP EXIGEANTS
        if (
            str_contains($descriptionLower, 'polyvalent') &&
            str_contains($descriptionLower, 'autonome') &&
            str_contains($descriptionLower, 'motivé')
        ) {
            $clarity -= 3;
            $remarks[] = "Évite une annonce trop générique. Remplace les adjectifs vagues par des missions concrètes et des livrables précis.";
        }

        // PERFORMANCE basée sur les candidatures
        if ($totalPostulations >= 10) {
            $competitiveness += 18;
            $strengths[] = "Le volume de candidatures indique déjà une bonne visibilité de l’offre.";
        } elseif ($totalPostulations >= 4) {
            $competitiveness += 8;
        } else {
            $remarks[] = "Le volume de candidatures semble encore faible. Une meilleure clarté du titre, plus de détails et plus de transparence peuvent aider.";
        }

        $clarity = $this->clamp($clarity);
        $attractiveness = $this->clamp($attractiveness);
        $transparency = $this->clamp($transparency);
        $competitiveness = $this->clamp($competitiveness);
        $credibility = $this->clamp($credibility);

        $globalScore = (int) round(
            ($clarity + $attractiveness + $transparency + $competitiveness + $credibility) / 5
        );

        $prediction = $this->buildPrediction($globalScore, $totalPostulations, $salary, $marketHits);

        if ($globalScore >= 80) {
            $level = 'Très fort potentiel';
            $levelColor = 'success';
        } elseif ($globalScore >= 65) {
            $level = 'Bon potentiel';
            $levelColor = 'info';
        } elseif ($globalScore >= 50) {
            $level = 'Potentiel moyen';
            $levelColor = 'warning';
        } else {
            $level = 'À retravailler';
            $levelColor = 'danger';
        }

        return [
            'stats' => [
                'total_postulations' => $totalPostulations,
                'accepted' => $accepted,
                'refused' => $refused,
                'pending' => $pending,
                'with_cv' => $withCv,
            ],
            'scores' => [
                'clarity' => $clarity,
                'attractiveness' => $attractiveness,
                'transparency' => $transparency,
                'competitiveness' => $competitiveness,
                'credibility' => $credibility,
                'global' => $globalScore,
            ],
            'strengths' => array_values(array_unique($strengths)),
            'weaknesses' => array_values(array_unique($weaknesses)),
            'remarks' => array_values(array_unique($remarks)),
            'prediction' => $prediction,
            'level' => $level,
            'level_color' => $levelColor,
            'sector' => $sector,
        ];
    }

    private function buildPrediction(int $globalScore, int $totalPostulations, mixed $salary, int $marketHits): string
    {
        if ($globalScore >= 80) {
            return "Cette offre présente un très bon niveau de compétitivité. Elle a de fortes chances d’attirer davantage de candidatures qualifiées si elle garde ce niveau de clarté et de transparence.";
        }

        if ($globalScore >= 65) {
            return "Cette offre est solide, mais elle pourrait gagner en performance avec quelques ajustements ciblés, surtout sur les éléments différenciants attendus par les candidats du marché actuel.";
        }

        if ($globalScore >= 50) {
            return "L’offre peut fonctionner, mais elle manque probablement de signaux assez forts pour se démarquer. En améliorant la précision, la transparence salariale et la proposition de valeur, son attractivité devrait progresser.";
        }

        if ($totalPostulations <= 2 && empty($salary) && $marketHits === 0) {
            return "Le modèle estime que l’offre risque de sous-performer si elle reste dans cet état. Elle a besoin d’un meilleur positionnement, de plus de détails concrets et d’arguments plus convaincants pour séduire les candidats.";
        }

        return "Le modèle estime que l’offre doit être retravaillée pour mieux correspondre aux attentes actuelles du marché et améliorer son taux de conversion en candidatures.";
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}