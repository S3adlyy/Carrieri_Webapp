<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class OllamaRecommendationService
{
    /** Resolve Python binary — works on any machine as long as Python is in PATH. */
    private static function resolvePythonBin(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return trim((string) shell_exec('which python3') ?: '') ?: 'python3';
        }

        // Windows: try 'python' from PATH first (works if Python is installed normally)
        $fromPath = trim((string) shell_exec('where python 2>NUL') ?: '');
        if ($fromPath !== '' && file_exists(explode("\n", $fromPath)[0])) {
            return explode("\n", $fromPath)[0];
        }

        // Fallback: common Windows install locations
        $candidates = [
            'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }

        return 'python'; // last resort — let the OS resolve it
    }

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Generate recommendation reasons for ALL courses in ONE Ollama call (batch mode).
     *
     * @param string   $candidateLevel
     * @param string[] $candidateSkills
     * @param array<int, array{
     *   course_title: string,
     *   course_skills: string[],
     *   course_level: string,
     *   course_duration: int,
     *   skill_matches: array{exact: int, partial: int}
     * }> $courses
     *
     * @return array<int, list<string>>  Indexed by same order as $courses
     */
    public function generateReasonsForAllCourses(
        string $candidateLevel,
        array  $candidateSkills,
        array  $courses,
    ): array {
        if ($courses === []) {
            return [];
        }

        $this->logger->info('OLLAMA: batch request', [
            'model'   => $_ENV['OLLAMA_MODEL'] ?? 'mistral:latest',
            'courses' => count($courses),
        ]);

        $input = json_encode([
            'candidate_level'  => $candidateLevel,
            'candidate_skills' => array_values($candidateSkills),
            'courses'          => array_values($courses),
        ]);

        if ($input === false) {
            $this->logger->error('OLLAMA: json_encode failed — returning fallback for all courses');
            return $this->buildFallbackForAll($candidateLevel, $candidateSkills, $courses);
        }

        $pythonScript = dirname(__DIR__, 2) . '/ollama_recommendations.py';
        $pythonBin    = self::resolvePythonBin();

        if (!file_exists($pythonScript) || !file_exists($pythonBin)) {
            $this->logger->error('OLLAMA: Python script or binary not found', [
                'script' => $pythonScript,
                'bin'    => $pythonBin,
            ]);
            return $this->buildFallbackForAll($candidateLevel, $candidateSkills, $courses);
        }

        try {
            $process = new Process([$pythonBin, '-u', $pythonScript]);
            $process->setInput($input);
            $process->setTimeout(180); // 3 min — Mistral needs time for a batch
            $process->setEnv([
                'PYTHONIOENCODING'           => 'utf-8',
                'PYTHONLEGACYWINDOWSSTDIO'   => '0',
                'OLLAMA_HOST'                => $_ENV['OLLAMA_HOST']    ?? 'http://127.0.0.1:11434',
                'OLLAMA_MODEL'               => $_ENV['OLLAMA_MODEL']   ?? 'mistral:latest',
                'OLLAMA_TIMEOUT'             => $_ENV['OLLAMA_TIMEOUT'] ?? '120',
            ]);
            $process->run();

            $stderr = $process->getErrorOutput();
            $rawOut = $process->getOutput();

            if (!mb_check_encoding($rawOut, 'UTF-8')) {
                $rawOut = mb_convert_encoding($rawOut, 'UTF-8', 'Windows-1252');
            }
            $stdout = trim($rawOut);

            $this->logger->info('OLLAMA: batch process finished', [
                'exit_code'  => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
                'stderr'     => $stderr,
            ]);

            if (!$process->isSuccessful()) {
                $this->logger->error('OLLAMA: Process failed', ['stderr' => $stderr]);
                return $this->buildFallbackForAll($candidateLevel, $candidateSkills, $courses);
            }

            $decoded = json_decode($stdout, true);

            if (
                is_array($decoded)
                && isset($decoded['results'])
                && is_array($decoded['results'])
            ) {
                $source  = $decoded['source'] ?? 'unknown';
                $results = $decoded['results'];

                $this->logger->info('OLLAMA: batch parsed', [
                    'source'  => $source,
                    'courses' => count($results),
                ]);

                // Map results back by index
                $reasonsByIndex = [];
                foreach ($results as $i => $item) {
                    $reasons = array_values(array_filter(
                        (array) ($item['reasons'] ?? []),
                        static fn ($r): bool => is_string($r) && trim($r) !== ''
                    ));
                    $reasonsByIndex[$i] = $reasons !== [] ? $reasons : ['Recommandé selon votre profil.'];
                }

                return $reasonsByIndex;
            }

            $this->logger->warning('OLLAMA: Could not parse batch output', ['stdout' => $stdout]);

        } catch (\Throwable $e) {
            $this->logger->error('OLLAMA: Exception', ['message' => $e->getMessage()]);
        }

        $this->logger->warning('OLLAMA: Using PHP fallback for all courses');
        return $this->buildFallbackForAll($candidateLevel, $candidateSkills, $courses);
    }

    /**
     * @param array<int, array{course_level: string, course_duration: int, skill_matches: array{exact: int, partial: int}}> $courses
     * @param string[] $candidateSkills
     * @return array<int, list<string>>
     */
    private function buildFallbackForAll(string $candidateLevel, array $candidateSkills, array $courses): array
    {
        $result = [];
        foreach ($courses as $i => $course) {
            $result[$i] = $this->getFallbackReasons(
                $candidateLevel,
                $course['course_level'],
                $course['skill_matches'],
                $course['course_duration'],
            );
        }
        return $result;
    }

    /**
     * @param array{exact: int, partial: int} $skillMatches
     * @return list<string>
     */
    private function getFallbackReasons(
        string $candidateLevel,
        string $courseLevel,
        array  $skillMatches,
        int    $courseDuration,
    ): array {
        $reasons        = [];
        $exactMatches   = $skillMatches['exact'];
        $partialMatches = $skillMatches['partial'];

        if ($courseLevel === $candidateLevel) {
            $reasons[] = 'Cours adapté à votre niveau actuel';
        } elseif ($this->isNextLevel($candidateLevel, $courseLevel)) {
            $reasons[] = 'Excellent palier pour progresser';
        }

        if ($exactMatches > 0) {
            $reasons[] = sprintf('Correspond à %d compétence(s) déjà acquise(s)', $exactMatches);
        } elseif ($partialMatches > 0) {
            $reasons[] = 'Renforce des compétences proches de votre profil';
        }

        if ($courseDuration > 0 && $courseDuration <= 8) {
            $reasons[] = 'Format court pour monter rapidement en compétence';
        }

        return $reasons !== [] ? $reasons : ['Recommandé selon votre progression récente'];
    }

    private function isNextLevel(string $currentLevel, string $targetLevel): bool
    {
        $weights = ['debutant' => 1, 'intermediaire' => 2, 'avance' => 3, 'expert' => 4];
        $current = $weights[$this->normalizeLevel($currentLevel)] ?? 1;
        $target  = $weights[$this->normalizeLevel($targetLevel)]  ?? 1;
        return $target === ($current + 1);
    }

    private function normalizeLevel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['é','è','ê','ë','à','â','î','ï','ô','û','ù','ç'],
            ['e','e','e','e','a','a','i','i','o','u','u','c'],
            $value
        );
        return match (true) {
            str_contains($value, 'debut')  => 'debutant',
            str_contains($value, 'inter')  => 'intermediaire',
            str_contains($value, 'avan')   => 'avance',
            str_contains($value, 'expert') => 'expert',
            default                        => 'debutant',
        };
    }
}
