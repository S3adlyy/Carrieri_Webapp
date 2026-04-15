<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cours;
use App\Entity\Module;
use App\Entity\QuestionQuiz;
use App\Entity\QuestionTest;
use App\Entity\Reponse;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\QuestionQuizRepository;
use App\Repository\QuestionTestRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AssessmentAutoGeneratorService
{
    /** @var string[] */
    private const STOP_WORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'd', 'et', 'ou', 'mais', 'donc', 'car', 'ni', 'or',
        'a', 'au', 'aux', 'en', 'dans', 'sur', 'sous', 'avec', 'sans', 'pour', 'par', 'ce', 'cet', 'cette', 'ces',
        'est', 'sont', 'etre', 'etre', 'qui', 'que', 'quoi', 'dont', 'ainsi', 'comme', 'plus', 'moins', 'tres',
    ];

    public function __construct(
        private LeconRepository $leconRepository,
        private ModuleRepository $moduleRepository,
        private QuestionQuizRepository $questionQuizRepository,
        private QuestionTestRepository $questionTestRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureModuleQuizGenerated(Module $module, int $expected = 5): void
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            return;
        }

        $existing = $this->questionQuizRepository->findByModuleOrdered($moduleId, 200);
        $existingCount = count($existing);
        if ($existingCount >= $expected) {
            return;
        }

        $lessons = $this->leconRepository->findBy(['moduleId' => $moduleId], ['ordre' => 'ASC', 'id' => 'ASC']);
        $sentences = $this->extractSentencesFromLessons($lessons);
        $questions = $this->buildQuestionsFromSentences($sentences, $expected - $existingCount, array_keys($this->collectQuestionFingerprints($existing)));

        if ($questions !== []) {
            $this->saveQuizQuestions($module, $questions);
        }
    }

    public function ensureCoursFinalTestGenerated(Cours $cours, int $expected = 15): void
    {
        $coursId = $cours->getId();
        if ($coursId === null) {
            return;
        }

        $modules = $this->moduleRepository->findByCours($cours);
        $quizQuestionFingerprints = [];

        foreach ($modules as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $this->ensureModuleQuizGenerated($module, 5);

            $moduleQuizQuestions = $this->questionQuizRepository->findByModuleOrdered($moduleId, 200);
            foreach ($moduleQuizQuestions as $question) {
                $text = (string) $question->getQuestionText();
                if ($text !== '') {
                    $quizQuestionFingerprints[$this->normalizeText($text)] = true;
                }
            }
        }

        $testQuestions = $this->questionTestRepository->findByCoursOrdered($coursId, 200);
        $existingCount = count($testQuestions);
        if ($existingCount >= $expected) {
            return;
        }

        $allSentences = [];
        foreach ($modules as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $lessons = $this->leconRepository->findBy(['moduleId' => $moduleId], ['ordre' => 'ASC', 'id' => 'ASC']);
            $allSentences = [...$allSentences, ...$this->extractSentencesFromLessons($lessons)];
        }

        $questions = $this->buildQuestionsFromSentences(
            $allSentences,
            $expected - $existingCount,
            array_merge(
                array_keys($this->collectQuestionFingerprints($testQuestions)),
                array_keys($quizQuestionFingerprints),
            )
        );

        if ($questions !== []) {
            $this->saveTestQuestions($cours, $questions);
        }
    }

    /**
     * @param list<object> $questions
     * @return array<string, bool>
     */
    private function collectQuestionFingerprints(array $questions): array
    {
        $fingerprints = [];

        foreach ($questions as $question) {
            $text = trim((string) ($question->getQuestionText() ?? ''));
            if ($text === '') {
                continue;
            }

            $fingerprints[$this->normalizeText($text)] = true;
        }

        return $fingerprints;
    }

    /**
     * @param list<object> $lessons
     * @return string[]
     */
    private function extractSentencesFromLessons(array $lessons): array
    {
        $sentences = [];

        foreach ($lessons as $lesson) {
            $content = (string) $lesson->getContenu();
            if ($content === '') {
                continue;
            }

            $clean = strip_tags($content);
            $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
            $parts = preg_split('/[.!?]+/u', $clean) ?: [];

            foreach ($parts as $part) {
                $line = trim($part);
                if (mb_strlen($line) < 35) {
                    continue;
                }

                $sentences[] = $line;
            }
        }

        return array_values(array_unique($sentences));
    }

    /**
     * @param string[] $sentences
     * @param string[] $excludedFingerprints
     * @return list<array{question:string,correct:string,choices:string[],points:int}>
     */
    private function buildQuestionsFromSentences(array $sentences, int $limit, array $excludedFingerprints): array
    {
        $sentences = array_values(array_unique($sentences));
        shuffle($sentences);

        $out = [];
        $used = array_fill_keys($excludedFingerprints, true);
        $usedSentences = [];
        $wordPool = $this->buildWordPool($sentences);

        $targets = [
            'completion' => max(1, (int) ceil($limit * 0.4)),
            'true_false' => max(1, (int) ceil($limit * 0.3)),
            'statement_mcq' => max(1, $limit - (int) ceil($limit * 0.4) - (int) ceil($limit * 0.3)),
        ];
        $counts = ['completion' => 0, 'true_false' => 0, 'statement_mcq' => 0];

        foreach ($sentences as $sentence) {
            if (count($out) >= $limit) {
                break;
            }

            $sentenceFingerprint = $this->normalizeText($sentence);
            if (isset($usedSentences[$sentenceFingerprint])) {
                continue;
            }

            foreach ($this->getGenerationOrder($targets, $counts) as $type) {
                $questionData = match ($type) {
                    'completion' => $this->createCompletionQuestion($sentence, $wordPool, $used),
                    'true_false' => $this->createTrueFalseQuestion($sentence, $used),
                    'statement_mcq' => $this->createStatementMcqQuestion($sentence, $sentences, $used),
                    default => null,
                };

                if ($questionData !== null) {
                    $out[] = $questionData;
                    $counts[$type]++;
                    $usedSentences[$sentenceFingerprint] = true;
                    break;
                }
            }
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * @param array<string, int> $targets
     * @param array<string, int> $counts
     * @return string[]
     */
    private function getGenerationOrder(array $targets, array $counts): array
    {
        $types = array_keys($targets);
        usort($types, static function (string $a, string $b) use ($targets, $counts): int {
            $deficitA = $targets[$a] - $counts[$a];
            $deficitB = $targets[$b] - $counts[$b];

            return $deficitB <=> $deficitA;
        });

        return $types;
    }

    /**
     * @param string[] $wordPool
     * @param array<string, bool> $used
     * @return array{question:string,correct:string,choices:string[],points:int}|null
     */
    private function createCompletionQuestion(string $sentence, array $wordPool, array &$used): ?array
    {
        $tokens = $this->extractCandidateWords($sentence);
        if ($tokens === []) {
            return null;
        }

        $targetWord = $tokens[array_rand($tokens)];
        $pattern = '/\b' . preg_quote($targetWord, '/') . '\b/ui';
        $blanked = preg_replace($pattern, '______', $sentence, 1);
        if ($blanked === null || $blanked === $sentence) {
            return null;
        }

        $questionText = 'Completez la phrase : ' . trim($blanked);
        $fingerprint = $this->normalizeText($questionText);
        if (isset($used[$fingerprint])) {
            return null;
        }

        $choices = $this->buildChoices($targetWord, $wordPool);
        if (count($choices) < 2) {
            return null;
        }

        $used[$fingerprint] = true;

        return [
            'question' => $questionText,
            'correct' => $targetWord,
            'choices' => $choices,
            'points' => 1,
        ];
    }

    /**
     * @param array<string, bool> $used
     * @return array{question:string,correct:string,choices:string[],points:int}|null
     */
    private function createTrueFalseQuestion(string $sentence, array &$used): ?array
    {
        $isTrue = random_int(0, 1) === 1;
        $statement = $isTrue ? trim($sentence) : $this->makeFalseSentence(trim($sentence));
        if ($statement === '') {
            return null;
        }

        $questionText = 'Vrai ou Faux : ' . $statement;
        $fingerprint = $this->normalizeText($questionText);
        if (isset($used[$fingerprint])) {
            return null;
        }

        $used[$fingerprint] = true;

        return [
            'question' => $questionText,
            'correct' => $isTrue ? 'Vrai' : 'Faux',
            'choices' => ['Vrai', 'Faux'],
            'points' => 1,
        ];
    }

    /**
     * @param string[] $allSentences
     * @param array<string, bool> $used
     * @return array{question:string,correct:string,choices:string[],points:int}|null
     */
    private function createStatementMcqQuestion(string $sentence, array $allSentences, array &$used): ?array
    {
        $base = trim($sentence);
        if ($base === '') {
            return null;
        }

        $questionText = 'Choisissez l\'affirmation correcte :';
        $fingerprint = $this->normalizeText($questionText . ' ' . $base);
        if (isset($used[$fingerprint])) {
            return null;
        }

        $choices = [$base];

        $false1 = $this->makeFalseSentence($base);
        if ($false1 !== '' && $this->normalizeText($false1) !== $this->normalizeText($base)) {
            $choices[] = $false1;
        }

        $false2 = $this->makeFalseSentence($false1 !== '' ? $false1 : $base);
        if ($false2 !== '' && $this->normalizeText($false2) !== $this->normalizeText($base)) {
            $choices[] = $false2;
        }

        foreach ($allSentences as $candidateSentence) {
            if (count($choices) >= 4) {
                break;
            }

            $candidate = trim($candidateSentence);
            if ($candidate === '' || $this->normalizeText($candidate) === $this->normalizeText($base)) {
                continue;
            }

            $choices[] = $candidate;
        }

        $choices = $this->normalizeChoiceSet($choices, $base);
        if (count($choices) < 2) {
            return null;
        }

        $used[$fingerprint] = true;

        return [
            'question' => $questionText,
            'correct' => $base,
            'choices' => $choices,
            'points' => 1,
        ];
    }

    private function makeFalseSentence(string $sentence): string
    {
        $replacements = [
            '/\best\b/ui' => 'n\'est pas',
            '/\bsont\b/ui' => 'ne sont pas',
            '/\bpeut\b/ui' => 'ne peut pas',
            '/\bpermet\b/ui' => 'n\'autorise pas',
            '/\balways\b/ui' => 'never',
            '/\btrue\b/ui' => 'false',
        ];

        foreach ($replacements as $pattern => $replace) {
            $changed = preg_replace($pattern, $replace, $sentence, 1);
            if ($changed !== null && $changed !== $sentence) {
                return $changed;
            }
        }

        if ($sentence !== '') {
            return 'Il est faux que ' . lcfirst($sentence);
        }

        return '';
    }

    /**
     * @param string[] $choices
     * @return string[]
     */
    private function normalizeChoiceSet(array $choices, string $correct): array
    {
        $unique = [];
        foreach ($choices as $choice) {
            $key = $this->normalizeText($choice);
            if ($key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $choice;
        }

        if (!isset($unique[$this->normalizeText($correct)])) {
            $unique[$this->normalizeText($correct)] = $correct;
        }

        $finalChoices = array_values($unique);
        $finalChoices = array_slice($finalChoices, 0, 4);
        shuffle($finalChoices);

        return $finalChoices;
    }

    /**
     * @param string[] $sentences
     * @return string[]
     */
    private function buildWordPool(array $sentences): array
    {
        $pool = [];
        foreach ($sentences as $sentence) {
            foreach ($this->extractCandidateWords($sentence) as $word) {
                $pool[$this->normalizeText($word)] = $word;
            }
        }

        return array_values($pool);
    }

    /**
     * @return string[]
     */
    private function extractCandidateWords(string $sentence): array
    {
        $words = preg_split('/\s+/u', $sentence) ?: [];
        $out = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}\-]/u', '', $word) ?? '';
            $lower = mb_strtolower($clean);
            if (mb_strlen($clean) < 4) {
                continue;
            }

            if (in_array($lower, self::STOP_WORDS, true)) {
                continue;
            }

            $out[] = $clean;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param string[] $wordPool
     * @return string[]
     */
    private function buildChoices(string $correct, array $wordPool): array
    {
        $choices = [];
        $seen = [];
        $correctFingerprint = $this->normalizeText($correct);

        $this->appendUniqueChoice($choices, $seen, $correct);

        foreach ($wordPool as $candidate) {
            if (count($choices) >= 4) {
                break;
            }

            if ($this->normalizeText($candidate) === $correctFingerprint) {
                continue;
            }

            $this->appendUniqueChoice($choices, $seen, $candidate);
        }

        while (count($choices) < 4) {
            $this->appendUniqueChoice($choices, $seen, 'Option ' . (count($choices) + 1));
        }

        shuffle($choices);

        return array_values($choices);
    }

    /**
     * @param string[] $choices
     * @param array<string, bool> $seen
     */
    private function appendUniqueChoice(array &$choices, array &$seen, string $choice): void
    {
        $fingerprint = $this->normalizeText($choice);
        if ($fingerprint === '' || isset($seen[$fingerprint])) {
            return;
        }

        $seen[$fingerprint] = true;
        $choices[] = $choice;
    }

    /**
     * @param list<array{question:string,correct:string,choices:string[],points:int}> $questions
     */
    private function saveQuizQuestions(Module $module, array $questions): void
    {
        $moduleId = $module->getId();
        if ($moduleId === null || $questions === []) {
            return;
        }

        $order = 1;
        foreach ($questions as $data) {
            $question = (new QuestionQuiz())
                ->setModuleId($moduleId)
                ->setModule($module)
                ->setQuestionText($data['question'])
                ->setPoints($data['points'])
                ->setOrdre($order++);

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $questionId = $question->getId();
            if ($questionId === null) {
                continue;
            }

            $answerOrder = 1;
            foreach ($data['choices'] as $choice) {
                $answer = (new Reponse())
                    ->setQuestionId($questionId)
                    ->setQuestionType('QUIZ')
                    ->setReponseText($choice)
                    ->setEstCorrecte($choice === $data['correct'] ? 1 : 0)
                    ->setOrdre($answerOrder++);

                $this->entityManager->persist($answer);
            }

            $this->entityManager->flush();
        }
    }

    /**
     * @param list<array{question:string,correct:string,choices:string[],points:int}> $questions
     */
    private function saveTestQuestions(Cours $cours, array $questions): void
    {
        $coursId = $cours->getId();
        if ($coursId === null || $questions === []) {
            return;
        }

        $order = 1;
        foreach ($questions as $data) {
            $question = (new QuestionTest())
                ->setCoursId($coursId)
                ->setCours($cours)
                ->setQuestionText($data['question'])
                ->setPoints($data['points'])
                ->setOrdre($order++);

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $questionId = $question->getId();
            if ($questionId === null) {
                continue;
            }

            $answerOrder = 1;
            foreach ($data['choices'] as $choice) {
                $answer = (new Reponse())
                    ->setQuestionId($questionId)
                    ->setQuestionType('TEST')
                    ->setReponseText($choice)
                    ->setEstCorrecte($choice === $data['correct'] ? 1 : 0)
                    ->setOrdre($answerOrder++);

                $this->entityManager->persist($answer);
            }

            $this->entityManager->flush();
        }
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
    }
}

