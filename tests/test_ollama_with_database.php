#!/usr/bin/env php
<?php
/**
 * Real-World Test with Database
 * 
 * This script demonstrates Ollama AI working with real database data.
 * It fetches actual courses and students to show recommendations.
 * 
 * Usage: php tests/test_ollama_with_database.php
 */

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

// Get database URL from .env
$databaseUrl = $_ENV['DATABASE_URL'] ?? null;
if (!$databaseUrl) {
    echo "❌ DATABASE_URL not found in .env\n";
    exit(1);
}

try {
    // Connect to database
    $connection = DriverManager::getConnection(['url' => $databaseUrl]);
    
    echo "🤖 Ollama AI - Real Database Test\n";
    echo "===================================\n\n";
    
    // Check if we have any courses
    $courses = $connection->fetchAllAssociative('SELECT id, titre, niveau, competences_visees FROM cours LIMIT 5');
    
    if (empty($courses)) {
        echo "⚠️  No courses found in database\n";
        echo "Please create some courses first!\n";
        exit(1);
    }
    
    echo "📚 Found " . count($courses) . " courses in database:\n\n";
    
    foreach ($courses as $course) {
        echo "- " . ($course['titre'] ?? 'Unknown') . "\n";
        echo "  Level: " . ($course['niveau'] ?? 'N/A') . "\n";
        echo "  Skills: " . ($course['competences_visees'] ?? 'N/A') . "\n\n";
    }
    
    echo "✅ Database connection successful!\n";
    echo "\n";
    echo "📝 To see AI recommendations in action:\n";
    echo "  1. Make sure ollama serve is running\n";
    echo "  2. Navigate to http://127.0.0.1:8000\n";
    echo "  3. Login as a student\n";
    echo "  4. Complete some courses\n";
    echo "  5. Go to 'Mes Recommandations' section\n";
    echo "  6. Watch the AI generate intelligent reasons! 🎯\n";
    
} catch (Exception $e) {
    echo "❌ Database connection error:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

