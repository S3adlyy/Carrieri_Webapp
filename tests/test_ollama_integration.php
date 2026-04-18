#!/usr/bin/env php
<?php
/**
 * Ollama AI Integration Test
 * 
 * This script tests the Ollama integration for course recommendations.
 * Run: php tests/test_ollama_integration.php
 */

use App\Service\OllamaRecommendationService;

require_once __DIR__ . '/../vendor/autoload.php';

// Create service instance
$service = new OllamaRecommendationService();

echo "🤖 Ollama AI Integration Test\n";
echo "==============================\n\n";

// Test 1: Basic recommendation
echo "Test 1: Generate reasons for JavaScript → React recommendation\n";
echo "--------------------------------------------------------------\n";

$reasons = $service->generateReasons(
    candidateSkills: ['JavaScript', 'HTML', 'CSS'],
    candidateLevel: 'Intermédiaire',
    courseTitle: 'React Avancé',
    courseSkills: ['JavaScript', 'React', 'JSX'],
    courseLevel: 'Avancé',
    courseDuration: 8,
    skillMatches: ['exact' => 1, 'partial' => 0],
);

echo "Generated reasons:\n";
foreach ($reasons as $i => $reason) {
    echo "  " . ($i + 1) . ". " . $reason . "\n";
}
echo "\n";

// Test 2: Different skill profile
echo "Test 2: Generate reasons for Python → Data Science recommendation\n";
echo "-------------------------------------------------------------------\n";

$reasons = $service->generateReasons(
    candidateSkills: ['Python', 'SQL'],
    candidateLevel: 'Débutant',
    courseTitle: 'Data Science avec Python',
    courseSkills: ['Python', 'Pandas', 'NumPy', 'Scikit-learn'],
    courseLevel: 'Intermédiaire',
    courseDuration: 12,
    skillMatches: ['exact' => 1, 'partial' => 0],
);

echo "Generated reasons:\n";
foreach ($reasons as $i => $reason) {
    echo "  " . ($i + 1) . ". " . $reason . "\n";
}
echo "\n";

// Test 3: No exact skill match
echo "Test 3: Generate reasons with partial skill match only\n";
echo "------------------------------------------------------\n";

$reasons = $service->generateReasons(
    candidateSkills: ['PHP', 'Laravel'],
    candidateLevel: 'Avancé',
    courseTitle: 'Symfony 6 Mastery',
    courseSkills: ['PHP', 'Symfony', 'Database Design'],
    courseLevel: 'Avancé',
    courseDuration: 10,
    skillMatches: ['exact' => 1, 'partial' => 0],
);

echo "Generated reasons:\n";
foreach ($reasons as $i => $reason) {
    echo "  " . ($i + 1) . ". " . $reason . "\n";
}
echo "\n";

echo "✅ Tests completed!\n";
echo "\n";
echo "📝 Notes:\n";
echo "  - If Ollama is running, you'll see AI-generated reasons\n";
echo "  - If Ollama is not running, you'll see fallback reasons\n";
echo "  - Both are equally valid - Ollama is for enhanced UX\n";
echo "\n";
echo "🚀 To enable Ollama:\n";
echo "  1. Install Ollama from https://ollama.ai\n";
echo "  2. Run: ollama pull mistral\n";
echo "  3. Run: ollama serve\n";
echo "  4. Re-run this test\n";

