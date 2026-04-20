<?php

namespace App\Service\AI;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;

class UrgencyDetectionService
{
    private array $weights = [];
    private array $vocabulary = [];
    private array $bias = [];

    public function __construct(
        private EntityManagerInterface $em,
        private ReclamationRepository $reclamationRepository
    ) {
        $this->loadModel();
        
        if (empty($this->weights)) {
            $this->train();
        }
    }

    public function detectUrgency(Reclamation $reclamation): array
    {
        $text = $reclamation->getObjet() . ' ' . $reclamation->getDescription();
        $features = $this->extractFeatures($text);
        $score = $this->predict($features);
        
        return [
            'score' => $score,
            'niveau' => $this->getUrgencyLevel($score),
            'probabilite' => $score
        ];
    }

    public function analyzeAndSortReclamations(array $reclamations): array
    {
        $results = [];
        
        foreach ($reclamations as $reclamation) {
            $urgency = $this->detectUrgency($reclamation);
            $results[] = [
                'reclamation' => $reclamation,
                'urgency_score' => $urgency['score'],
                'urgency_level' => $urgency['niveau'],
                'probability' => $urgency['probabilite']
            ];
        }
        
        usort($results, function($a, $b) {
            return $b['urgency_score'] <=> $a['urgency_score'];
        });
        
        return $results;
    }

    public function trainWithRealData(): array
{
    $trainingData = [];
    
    // 1. Récupérer les corrections manuelles
    $correctionFile = __DIR__ . '/../../../var/models/corrections.json';
    if (file_exists($correctionFile)) {
        $corrections = json_decode(file_get_contents($correctionFile), true);
        foreach ($corrections as $correction) {
            $trainingData[] = [$correction['text'], $correction['score']];
        }
    }
    
    // 2. Récupérer les réclamations traitées
    $reclamations = $this->reclamationRepository->findBy(['statut' => 'Traité']);
    foreach ($reclamations as $reclamation) {
        $text = $reclamation->getObjet() . ' ' . $reclamation->getDescription();
        $priorite = $reclamation->getPriorite();
        if ($priorite === 'Haute') {
            $score = 85;
        } elseif ($priorite === 'Moyenne') {
            $score = 55;
        } else {
            $score = 25;
        }
        $trainingData[] = [$text, $score];
    }
    
    if (count($trainingData) < 3) {
        return ['error' => 'Pas assez de données.'];
    }
    
    $this->retrain($trainingData);
    
    return [
        'success' => true,
        'samples_used' => count($trainingData),
        'message' => 'IA ré-entraînée avec ' . count($trainingData) . ' exemples'
    ];
}

    private function retrain(array $trainingData): void
    {
        // Construire le vocabulaire
        $vocabSet = [];
        foreach ($trainingData as $data) {
            $words = explode(' ', $this->cleanText($data[0]));
            foreach ($words as $word) {
                if (strlen($word) > 2) {
                    $vocabSet[$word] = true;
                }
            }
        }
        
        $this->vocabulary = array_keys($vocabSet);
        $this->weights = array_fill(0, count($this->vocabulary), 0);
        $this->bias = [0];
        
        // Entraînement par descente de gradient
        $learningRate = 0.01;
        
        for ($epoch = 0; $epoch < 500; $epoch++) {
            foreach ($trainingData as $data) {
                $features = $this->extractFeatures($data[0]);
                $predicted = $this->predict($features);
                $error = $data[1] - $predicted;
                
                // Mise à jour des poids
                foreach ($features as $index => $value) {
                    if (isset($this->weights[$index])) {
                        $this->weights[$index] += $learningRate * $error * min($value, 3);
                    }
                }
                // Mise à jour du biais
                $this->bias[0] += $learningRate * $error;
            }
        }
        
        $this->saveModel();
    }

    private function train(): void
    {
        $trainingData = [
            ["urgence besoin aide rapidement", 90],
            ["probleme systeme erreur critique", 88],
            ["cours manquant video inaccessible", 75],
            ["bug application plante souvent", 70],
            ["question simple sur le cours", 40],
            ["information sur la mission", 35],
            ["merci pour votre aide", 20],
            ["bravo excellent travail", 15],
            ["suggestion pour amelioration", 25],
            ["felicitations a toute equipe", 10],
        ];
        
        $this->retrain($trainingData);
    }

    private function extractFeatures(string $text): array
    {
        $text = $this->cleanText($text);
        
        $features = [];
        foreach ($this->vocabulary as $index => $word) {
            $features[$index] = substr_count($text, $word);
        }
        
        return $features;
    }

    private function predict(array $features): int
    {
        $score = $this->bias[0] ?? 0;
        
        foreach ($features as $index => $value) {
            if (isset($this->weights[$index])) {
                $score += $this->weights[$index] * min($value, 3);
            }
        }
        
        // Fonction d'activation sigmoïde puis mise à l'échelle 0-100
        $score = 100 / (1 + exp(-$score / 50));
        
        return max(0, min(100, (int)$score));
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function getUrgencyLevel(int $score): string
    {
        if ($score >= 80) return 'Critique';
        if ($score >= 60) return 'Élevée';
        if ($score >= 40) return 'Moyenne';
        if ($score >= 20) return 'Faible';
        return 'Minimale';
    }

    private function saveModel(): void
    {
        $dir = __DIR__ . '/../../../var/models';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $data = [
            'weights' => $this->weights,
            'vocabulary' => $this->vocabulary,
            'bias' => $this->bias
        ];
        
        file_put_contents($dir . '/urgency_model.json', json_encode($data));
    }

    private function loadModel(): void
    {
        $file = __DIR__ . '/../../../var/models/urgency_model.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['weights'])) {
                $this->weights = $data['weights'];
                $this->vocabulary = $data['vocabulary'];
                $this->bias = $data['bias'] ?? [0];
            }
        }
    }
}