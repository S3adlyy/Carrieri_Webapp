<?php

namespace App\Controller\FrontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CodeExecutionController extends AbstractController
{
    #[Route('/execute-code', name: 'execute_code', methods: ['POST'])]
    public function executeCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';
        $language = $data['language'] ?? 'python';
        $mode = $data['mode'] ?? 'free';

        if ($mode === 'free') {
            return $this->executeFreeCode($code, $language);
        } else {
            return $this->executeTestMode($code, $language);
        }
    }

    private function executeFreeCode(string $code, string $language): JsonResponse
    {
        if ($language === 'python') {
            return $this->executePythonFree($code);
        } elseif ($language === 'javascript') {
            return $this->executeJavaScriptFree($code);
        } else {
            return $this->json([
                'success' => false,
                'error' => "Le langage $language n'est pas encore supporté pour l'exécution libre",
                'output' => null
            ]);
        }
    }

    private function executePythonFree(string $code): JsonResponse
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/code_' . uniqid() . '.py';

        // Nettoyer le code
        $fullCode = $code . "\n";

        file_put_contents($tempFile, $fullCode);

        // Détection de l'OS
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            // Commande pour Windows
            $command = 'python ' . escapeshellarg($tempFile) . ' 2>&1';
        } else {
            // Commande pour Linux/Mac avec timeout
            $command = 'timeout 5 python3 ' . escapeshellarg($tempFile) . ' 2>&1';
        }

        $output = shell_exec($command);
        $exitCode = $this->getLastExitCode();

        unlink($tempFile);

        if ($exitCode === 0 || $output !== null) {
            return $this->json([
                'success' => true,
                'output' => $output ?: "(Pas de sortie)"
            ]);
        } else {
            return $this->json([
                'success' => false,
                'error' => $output ?: "Erreur d'exécution. Vérifiez votre code Python.",
                'output' => null
            ]);
        }
    }

    private function executeJavaScriptFree(string $code): JsonResponse
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/code_' . uniqid() . '.js';

        file_put_contents($tempFile, $code);

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $command = 'node ' . escapeshellarg($tempFile) . ' 2>&1';
        } else {
            $command = 'timeout 5 node ' . escapeshellarg($tempFile) . ' 2>&1';
        }

        $output = shell_exec($command);
        $exitCode = $this->getLastExitCode();

        unlink($tempFile);

        if ($exitCode === 0 || $output !== null) {
            return $this->json([
                'success' => true,
                'output' => $output ?: "(Pas de sortie)"
            ]);
        } else {
            return $this->json([
                'success' => false,
                'error' => $output ?: "Erreur d'exécution",
                'output' => null
            ]);
        }
    }

    private function executeTestMode(string $code, string $language): JsonResponse
    {
        $testCases = [
            [
                'input' => ['nums' => [2, 7, 11, 15], 'target' => 9],
                'expected' => [0, 1]
            ],
            [
                'input' => ['nums' => [3, 2, 4], 'target' => 6],
                'expected' => [1, 2]
            ],
            [
                'input' => ['nums' => [3, 3], 'target' => 6],
                'expected' => [0, 1]
            ]
        ];

        $results = [];
        $passedTests = 0;

        foreach ($testCases as $index => $testCase) {
            $result = $this->runCodeTest($code, $language, $testCase['input'], $testCase['expected']);
            $results[] = $result;
            if ($result['passed']) {
                $passedTests++;
            }
        }

        $totalTests = count($testCases);
        $score = ($passedTests / $totalTests) * 100;

        return $this->json([
            'success' => $passedTests === $totalTests,
            'passedTests' => $passedTests,
            'totalTests' => $totalTests,
            'score' => round($score),
            'results' => $results
        ]);
    }

    private function runCodeTest(string $code, string $language, array $input, array $expected): array
    {
        if ($language === 'python') {
            return $this->runPythonTest($code, $input, $expected);
        } elseif ($language === 'javascript') {
            return $this->runJavaScriptTest($code, $input, $expected);
        } else {
            return [
                'passed' => false,
                'message' => "Le langage $language n'est pas encore supporté",
                'expected' => $expected,
                'output' => null
            ];
        }
    }

    private function runPythonTest(string $code, array $input, array $expected): array
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/code_' . uniqid() . '.py';

        // Vérifier si le code contient une fonction twoSum
        $hasTwoSum = preg_match('/def\s+twoSum\s*\(/', $code);

        $fullCode = $code . "\n\n";
        $fullCode .= "# Test case\n";
        $fullCode .= "import json\n";
        $fullCode .= "import sys\n\n";

        if (!$hasTwoSum) {
            $fullCode .= "# Ajout d'une fonction twoSum par défaut\n";
            $fullCode .= "def twoSum(nums, target):\n";
            $fullCode .= "    seen = {}\n";
            $fullCode .= "    for i, num in enumerate(nums):\n";
            $fullCode .= "        complement = target - num\n";
            $fullCode .= "        if complement in seen:\n";
            $fullCode .= "            return [seen[complement], i]\n";
            $fullCode .= "        seen[num] = i\n";
            $fullCode .= "    return []\n\n";
        }

        $fullCode .= "def run_test():\n";
        $fullCode .= "    try:\n";
        $fullCode .= "        nums = " . json_encode($input['nums']) . "\n";
        $fullCode .= "        target = " . $input['target'] . "\n";
        $fullCode .= "        result = twoSum(nums, target)\n";
        $fullCode .= "        expected = " . json_encode($expected) . "\n";
        $fullCode .= "        if result == expected:\n";
        $fullCode .= "            return {'passed': True, 'output': str(result)}\n";
        $fullCode .= "        else:\n";
        $fullCode .= "            return {'passed': False, 'output': str(result), 'expected': str(expected)}\n";
        $fullCode .= "    except Exception as e:\n";
        $fullCode .= "        return {'passed': False, 'error': str(e)}\n\n";
        $fullCode .= "result = run_test()\n";
        $fullCode .= "print(json.dumps(result))\n";

        file_put_contents($tempFile, $fullCode);

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $command = 'python ' . escapeshellarg($tempFile) . ' 2>&1';
        } else {
            $command = 'python3 ' . escapeshellarg($tempFile) . ' 2>&1';
        }

        $output = shell_exec($command);

        unlink($tempFile);

        $result = json_decode($output, true);

        if ($result && isset($result['passed'])) {
            if ($result['passed']) {
                return [
                    'passed' => true,
                    'message' => "✓ Succès ! Résultat: " . $result['output'],
                    'expected' => $expected,
                    'output' => $result['output']
                ];
            } else {
                $errorMsg = $result['error'] ?? "Résultat incorrect. Attendu: " . ($result['expected'] ?? json_encode($expected)) . ", Reçu: " . ($result['output'] ?? '');
                return [
                    'passed' => false,
                    'message' => "✗ " . $errorMsg,
                    'expected' => $expected,
                    'output' => $result['output'] ?? null
                ];
            }
        } else {
            return [
                'passed' => false,
                'message' => "✗ Erreur: " . ($output ?: "Erreur inconnue. Vérifiez la syntaxe de votre code."),
                'expected' => $expected,
                'output' => null
            ];
        }
    }

    private function runJavaScriptTest(string $code, array $input, array $expected): array
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/code_' . uniqid() . '.js';

        $hasTwoSum = preg_match('/function\s+twoSum\s*\(|var\s+twoSum\s*=|const\s+twoSum\s*=|let\s+twoSum\s*=/', $code);

        $fullCode = $code . "\n\n";

        if (!$hasTwoSum) {
            $fullCode .= "// Ajout d'une fonction twoSum par défaut\n";
            $fullCode .= "function twoSum(nums, target) {\n";
            $fullCode .= "    const map = new Map();\n";
            $fullCode .= "    for (let i = 0; i < nums.length; i++) {\n";
            $fullCode .= "        const complement = target - nums[i];\n";
            $fullCode .= "        if (map.has(complement)) {\n";
            $fullCode .= "            return [map.get(complement), i];\n";
            $fullCode .= "        }\n";
            $fullCode .= "        map.set(nums[i], i);\n";
            $fullCode .= "    }\n";
            $fullCode .= "    return [];\n";
            $fullCode .= "}\n\n";
        }

        $fullCode .= "// Test case\n";
        $fullCode .= "const nums = " . json_encode($input['nums']) . ";\n";
        $fullCode .= "const target = " . $input['target'] . ";\n";
        $fullCode .= "const expected = " . json_encode($expected) . ";\n\n";
        $fullCode .= "try {\n";
        $fullCode .= "    const result = twoSum(nums, target);\n";
        $fullCode .= "    const passed = JSON.stringify(result) === JSON.stringify(expected);\n";
        $fullCode .= "    console.log(JSON.stringify({passed, output: result, expected}));\n";
        $fullCode .= "} catch(error) {\n";
        $fullCode .= "    console.log(JSON.stringify({passed: false, error: error.message}));\n";
        $fullCode .= "}\n";

        file_put_contents($tempFile, $fullCode);

        $command = 'node ' . escapeshellarg($tempFile) . ' 2>&1';
        $output = shell_exec($command);

        unlink($tempFile);

        $result = json_decode($output, true);

        if ($result && isset($result['passed'])) {
            if ($result['passed']) {
                return [
                    'passed' => true,
                    'message' => "✓ Succès ! Résultat: " . json_encode($result['output']),
                    'expected' => $expected,
                    'output' => $result['output']
                ];
            } else {
                return [
                    'passed' => false,
                    'message' => "✗ " . ($result['error'] ?? "Test échoué"),
                    'expected' => $expected,
                    'output' => $result['output'] ?? null
                ];
            }
        } else {
            return [
                'passed' => false,
                'message' => "✗ Erreur: " . ($output ?: "Erreur inconnue"),
                'expected' => $expected,
                'output' => null
            ];
        }
    }

    private function getLastExitCode(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return (int)shell_exec("echo %errorlevel%");
        } else {
            return (int)shell_exec("echo $?");
        }
    }
}