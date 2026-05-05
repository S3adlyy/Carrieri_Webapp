<?php
// src/Service/MissionAnalyzerService.php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class MissionAnalyzerService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $aiApiBaseUrl = 'http://127.0.0.1:8001',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyzeMissionDescription(string $description, string $title = ''): array
    {
        // Essayer d'abord avec l'API IA
        $apiResult = $this->callAiApi($description, $title);

        if ($apiResult && isset($apiResult['examples']) && count($apiResult['examples']) > 0) {
            return $apiResult;
        }

        // Fallback: analyse basée sur des mots-clés
        return $this->generateBasicMissionData($description, $title);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callAiApi(string $description, string $title): ?array
    {
        try {
            $response = $this->httpClient->request('POST', $this->aiApiBaseUrl . '/analyze-mission', [
                'json' => [
                    'description' => $description,
                    'title' => $title
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();

            if (isset($data['examples']) && count($data['examples']) > 0) {
                return [
                    'success' => true,
                    'examples' => $data['examples'],
                    'constraints' => $data['constraints'] ?? $this->extractConstraints($description),
                    'function_name' => $data['function_name'] ?? $this->extractFunctionName($description),
                    'parameters' => $data['parameters'] ?? $this->extractParameters($description),
                    'return_type' => $data['return_type'] ?? 'mixed',
                    'test_cases' => $data['test_cases'] ?? []
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('API IA non disponible: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBasicMissionData(string $description, string $title): array
    {
        $lowerDesc = strtolower($description);
        $lowerTitle = strtolower($title);

        $examples = [];
        $constraints = [];
        $functionName = 'solution';
        $parameters = ['params'];
        $returnType = 'mixed';

        // Détection: Sum of two values / Two Sum
        if (str_contains($lowerDesc, 'sum of 2') ||
            str_contains($lowerDesc, 'two sum') ||
            str_contains($lowerDesc, 'somme de deux') ||
            str_contains($lowerDesc, 'addition')) {

            $examples = [
                [
                    'input' => 'nums = [2, 7, 11, 15], target = 9',
                    'output' => '[0, 1]',
                    'explanation' => 'nums[0] + nums[1] = 2 + 7 = 9 → indices [0, 1]'
                ],
                [
                    'input' => 'nums = [3, 2, 4], target = 6',
                    'output' => '[1, 2]',
                    'explanation' => 'nums[1] + nums[2] = 2 + 4 = 6 → indices [1, 2]'
                ],
                [
                    'input' => 'nums = [3, 3], target = 6',
                    'output' => '[0, 1]',
                    'explanation' => 'nums[0] + nums[1] = 3 + 3 = 6 → indices [0, 1]'
                ]
            ];

            $constraints = [
                '2 ≤ nums.length ≤ 10⁴',
                '-10⁹ ≤ nums[i] ≤ 10⁹',
                '-10⁹ ≤ target ≤ 10⁹',
                'Il existe exactement une solution',
                'Vous ne pouvez pas utiliser deux fois le même élément'
            ];

            $functionName = 'twoSum';
            $parameters = ['nums', 'target'];
            $returnType = 'List[int]';
        }
        // Détection: Palindrome
        elseif (str_contains($lowerDesc, 'palindrome')) {
            $examples = [
                [
                    'input' => 'x = 121',
                    'output' => 'true',
                    'explanation' => '121 se lit de la même manière à l\'endroit et à l\'envers'
                ],
                [
                    'input' => 'x = -121',
                    'output' => 'false',
                    'explanation' => '-121 ≠ 121- → false'
                ],
                [
                    'input' => 'x = 10',
                    'output' => 'false',
                    'explanation' => '10 ≠ 01 → false'
                ]
            ];
            $constraints = [
                '-2³¹ ≤ x ≤ 2³¹-1'
            ];
            $functionName = 'isPalindrome';
            $parameters = ['x'];
            $returnType = 'bool';
        }
        // Détection: Fibonacci
        elseif (str_contains($lowerDesc, 'fibonacci')) {
            $examples = [
                [
                    'input' => 'n = 0',
                    'output' => '0',
                    'explanation' => 'F(0) = 0'
                ],
                [
                    'input' => 'n = 1',
                    'output' => '1',
                    'explanation' => 'F(1) = 1'
                ],
                [
                    'input' => 'n = 5',
                    'output' => '5',
                    'explanation' => 'F(5) = F(4) + F(3) = 3 + 2 = 5'
                ]
            ];
            $constraints = [
                '0 ≤ n ≤ 30'
            ];
            $functionName = 'fib';
            $parameters = ['n'];
            $returnType = 'int';
        }
        // Détection: Moyenne / Average
        elseif (str_contains($lowerDesc, 'average') || str_contains($lowerDesc, 'moyenne')) {
            $examples = [
                [
                    'input' => 'numbers = [1, 2, 3, 4, 5]',
                    'output' => '3.0',
                    'explanation' => '(1+2+3+4+5)/5 = 3.0'
                ],
                [
                    'input' => 'numbers = [10, 20, 30]',
                    'output' => '20.0',
                    'explanation' => '(10+20+30)/3 = 20.0'
                ],
                [
                    'input' => 'numbers = []',
                    'output' => '0',
                    'explanation' => 'Tableau vide → retourne 0'
                ]
            ];
            $constraints = [
                '0 ≤ numbers.length ≤ 10⁴',
                '-10⁶ ≤ numbers[i] ≤ 10⁶'
            ];
            $functionName = 'calculateAverage';
            $parameters = ['numbers'];
            $returnType = 'float';
        }
        // Détection: Factorielle / Factorial
        elseif (str_contains($lowerDesc, 'factorial') || str_contains($lowerDesc, 'factorielle')) {
            $examples = [
                [
                    'input' => 'n = 0',
                    'output' => '1',
                    'explanation' => '0! = 1'
                ],
                [
                    'input' => 'n = 5',
                    'output' => '120',
                    'explanation' => '5! = 5×4×3×2×1 = 120'
                ],
                [
                    'input' => 'n = 3',
                    'output' => '6',
                    'explanation' => '3! = 3×2×1 = 6'
                ]
            ];
            $constraints = [
                '0 ≤ n ≤ 20'
            ];
            $functionName = 'factorial';
            $parameters = ['n'];
            $returnType = 'int';
        }
        // Détection: Tri / Sort
        elseif (str_contains($lowerDesc, 'sort') || str_contains($lowerDesc, 'tri')) {
            $examples = [
                [
                    'input' => 'arr = [3, 1, 4, 1, 5, 9, 2]',
                    'output' => '[1, 1, 2, 3, 4, 5, 9]',
                    'explanation' => 'Tri croissant du tableau'
                ],
                [
                    'input' => 'arr = [5, 2, 8, 1, 9]',
                    'output' => '[1, 2, 5, 8, 9]',
                    'explanation' => 'Tri croissant'
                ]
            ];
            $constraints = [
                '1 ≤ arr.length ≤ 10⁴',
                '-10⁶ ≤ arr[i] ≤ 10⁶'
            ];
            $functionName = 'sortArray';
            $parameters = ['arr'];
            $returnType = 'List[int]';
        }
        // Détection: Recherche / Search
        elseif (str_contains($lowerDesc, 'search') || str_contains($lowerDesc, 'recherche')) {
            $examples = [
                [
                    'input' => 'nums = [-1,0,3,5,9,12], target = 9',
                    'output' => '4',
                    'explanation' => '9 existe à l\'index 4'
                ],
                [
                    'input' => 'nums = [-1,0,3,5,9,12], target = 2',
                    'output' => '-1',
                    'explanation' => '2 n\'existe pas dans le tableau'
                ]
            ];
            $constraints = [
                '1 ≤ nums.length ≤ 10⁴',
                '-10⁴ ≤ nums[i] ≤ 10⁴',
                'Tous les éléments sont uniques',
                'nums est trié en ordre croissant'
            ];
            $functionName = 'search';
            $parameters = ['nums', 'target'];
            $returnType = 'int';
        }
        // Détection: FizzBuzz
        elseif (str_contains($lowerDesc, 'fizzbuzz') || str_contains($lowerDesc, 'fizz buzz')) {
            $examples = [
                [
                    'input' => 'n = 3',
                    'output' => '["1","2","Fizz"]',
                    'explanation' => '3 est divisible par 3 → "Fizz"'
                ],
                [
                    'input' => 'n = 5',
                    'output' => '["1","2","Fizz","4","Buzz"]',
                    'explanation' => '5 est divisible par 5 → "Buzz"'
                ],
                [
                    'input' => 'n = 15',
                    'output' => '... "FizzBuzz"',
                    'explanation' => '15 est divisible par 3 et 5 → "FizzBuzz"'
                ]
            ];
            $constraints = [
                '1 ≤ n ≤ 10⁴'
            ];
            $functionName = 'fizzBuzz';
            $parameters = ['n'];
            $returnType = 'List[str]';
        }
        // Générique avec extraction intelligente
        else {
            // Essayer d'extraire des exemples depuis la description
            $examples = $this->extractExamplesFromDescription($description);

            if (empty($examples)) {
                $examples = [
                    [
                        'input' => 'Exemple d\'entrée',
                        'output' => 'Sortie attendue',
                        'explanation' => 'Explication basée sur la description'
                    ]
                ];
            }

            $constraints = $this->extractConstraints($description);
            $functionName = $this->extractFunctionName($description);
            $parameters = $this->extractParameters($description);
        }

        return [
            'success' => true,
            'examples' => $examples,
            'constraints' => $constraints,
            'function_name' => $functionName,
            'parameters' => $parameters,
            'return_type' => $returnType,
            'test_cases' => []
        ];
    }

    /**
     * @return array<array<string, string>>
     */
    private function extractExamplesFromDescription(string $description): array
    {
        $examples = [];
        $lines = explode("\n", $description);

        $currentExample = [];
        foreach ($lines as $line) {
            $lowerLine = strtolower($line);

            if (str_contains($lowerLine, 'exemple') || str_contains($lowerLine, 'example')) {
                if (!empty($currentExample)) {
                    $examples[] = $currentExample;
                }
                $currentExample = ['input' => '', 'output' => '', 'explanation' => ''];
            }
            elseif (str_contains($lowerLine, 'input') && !empty($currentExample)) {
                $currentExample['input'] = trim(str_replace(['Input:', 'input:'], '', $line));
            }
            elseif (str_contains($lowerLine, 'output') && !empty($currentExample)) {
                $currentExample['output'] = trim(str_replace(['Output:', 'output:'], '', $line));
            }
            elseif (str_contains($lowerLine, 'explanation') && !empty($currentExample)) {
                $currentExample['explanation'] = trim(str_replace(['Explanation:', 'explanation:'], '', $line));
            }
        }

        if (!empty($currentExample) && (!empty($currentExample['input']) || !empty($currentExample['output']))) {
            $examples[] = $currentExample;
        }

        return $examples;
    }

    /**
     * @return array<string>
     */
    private function extractConstraints(string $description): array
    {
        $constraints = [];
        $lowerDesc = strtolower($description);

        // Contraintes communes
        if (str_contains($lowerDesc, 'array') || str_contains($lowerDesc, 'tableau') || str_contains($lowerDesc, 'list')) {
            $constraints[] = 'Le tableau peut contenir jusqu\'à 10⁴ éléments';
            $constraints[] = 'Les valeurs sont des entiers dans la plage -10⁹ à 10⁹';
        }

        if (str_contains($lowerDesc, 'string') || str_contains($lowerDesc, 'chaîne')) {
            $constraints[] = 'La longueur de la chaîne ne dépasse pas 10⁵ caractères';
        }

        if (empty($constraints)) {
            $constraints = [
                'Contrainte à définir selon le problème',
                'Vérifier les limites des entrées'
            ];
        }

        return $constraints;
    }

    private function extractFunctionName(string $description): string
    {
        $lowerDesc = strtolower($description);

        if (str_contains($lowerDesc, 'sum') || str_contains($lowerDesc, 'somme')) {
            return 'calculateSum';
        }
        if (str_contains($lowerDesc, 'average') || str_contains($lowerDesc, 'moyenne')) {
            return 'calculateAverage';
        }
        if (str_contains($lowerDesc, 'max') || str_contains($lowerDesc, 'maximum')) {
            return 'findMax';
        }
        if (str_contains($lowerDesc, 'min') || str_contains($lowerDesc, 'minimum')) {
            return 'findMin';
        }
        if (str_contains($lowerDesc, 'sort') || str_contains($lowerDesc, 'tri')) {
            return 'sortArray';
        }
        if (str_contains($lowerDesc, 'search') || str_contains($lowerDesc, 'recherche')) {
            return 'search';
        }

        return 'solution';
    }

    /**
     * @return array<string>
     */
    private function extractParameters(string $description): array
    {
        $lowerDesc = strtolower($description);

        if (str_contains($lowerDesc, 'array') || str_contains($lowerDesc, 'tableau') || str_contains($lowerDesc, 'list')) {
            if (str_contains($lowerDesc, 'target') || str_contains($lowerDesc, 'cible')) {
                return ['nums', 'target'];
            }
            return ['arr'];
        }

        if (str_contains($lowerDesc, 'string') || str_contains($lowerDesc, 'chaîne')) {
            return ['s'];
        }

        if (str_contains($lowerDesc, 'number') || str_contains($lowerDesc, 'nombre')) {
            return ['n'];
        }

        return ['params'];
    }
}