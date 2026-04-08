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
use App\Repository\ReponseRepository;
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
        private ReponseRepository $reponseRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureModuleQuizGenerated(Module $module, int $expected = 5): void
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            return;
        }

        $count = $this->questionQuizRepository->countForModule($moduleId);
        if ($count >= $expected) {
            return;
        }

        $this->purgeModuleQuiz($moduleId);

        $lessons = $this->leconRepository->findBy(['moduleId' => $moduleId], ['ordre' => 'ASC', 'id' => 'ASC']);
        $sentences = $this->extractSentencesFromLessons($lessons);
        $questions = $this->buildQuestionsFromSentences($sentences, $expected, []);

        $this->saveQuizQuestions($module, $questions);
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
        $hasOverlap = false;
        foreach ($testQuestions as $testQuestion) {
            $fingerprint = $this->normalizeText((string) $testQuestion->getQuestionText());
            if (isset($quizQuestionFingerprints[$fingerprint])) {
                $hasOverlap = true;
                break;
            }
        }

        if (
            $this->questionTestRepository->countForCours($coursId) >= $expected
            && !$hasOverlap
        ) {
            return;
        }

        $this->purgeCoursTest($coursId);

        $allSentences = [];
        foreach ($modules as $module) {
            $moduleId = $module->getId();
            if ($moduleId === null) {
                continue;
            }

            $lessons = $this->leconRepository->findBy(['moduleId' => $moduleId], ['ordre' => 'ASC', 'id' => 'ASC']);
            $allSentences = [...$allSentences, ...$this->extractSentencesFromLessons($lessons)];
        }

        $questions = $this->buildQuestionsFromSentences($allSentences, $expected, array_keys($quizQuestionFingerprints));
        $this->saveTestQuestions($cours, $questions);
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
        $out = [];
        $used = array_fill_keys($excludedFingerprints, true);
        $wordPool = $this->buildWordPool($sentences);

        foreach ($sentences as $sentence) {
            if (count($out) >= $limit) {
                break;
            }

            $tokens = $this->extractCandidateWords($sentence);
            if ($tokens === []) {
                continue;
            }

            $targetWord = $tokens[array_rand($tokens)];
            $pattern = '/\b' . preg_quote($targetWord, '/') . '\b/ui';
            $blanked = preg_replace($pattern, '______', $sentence, 1);
            if ($blanked === null || $blanked === $sentence) {
                continue;
            }

            $questionText = 'Completez la phrase : ' . trim($blanked);
            $fingerprint = $this->normalizeText($questionText);
            if (isset($used[$fingerprint])) {
                continue;
            }

            $choices = $this->buildChoices($targetWord, $wordPool);
            if (count($choices) < 2) {
                continue;
            }

            $used[$fingerprint] = true;
            $out[] = [
                'question' => $questionText,
                'correct' => $targetWord,
                'choices' => $choices,
                'points' => 1,
            ];
        }

        return $out;
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
        $choices = [$correct];
        $correctFingerprint = $this->normalizeText($correct);

        foreach ($wordPool as $candidate) {
            if (count($choices) >= 4) {
                break;
            }

            if ($this->normalizeText($candidate) === $correctFingerprint) {
                continue;
            }

            $choices[] = $candidate;
        }

        while (count($choices) < 4) {
            $choices[] = 'Option ' . count($choices);
        }

        shuffle($choices);

        return array_values(array_unique($choices));
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

    private function purgeModuleQuiz(int $moduleId): void
    {
        $questions = $this->questionQuizRepository->findBy(['moduleId' => $moduleId]);
        foreach ($questions as $question) {
            $questionId = $question->getId();
            if ($questionId !== null) {
                $answers = $this->reponseRepository->findByQuestionAndType($questionId, 'QUIZ');
                foreach ($answers as $answer) {
                    $this->entityManager->remove($answer);
                }
            }

            $this->entityManager->remove($question);
        }

        $this->entityManager->flush();
    }

    private function purgeCoursTest(int $coursId): void
    {
        $questions = $this->questionTestRepository->findBy(['coursId' => $coursId]);
        foreach ($questions as $question) {
            $questionId = $question->getId();
            if ($questionId !== null) {
                $answers = $this->reponseRepository->findByQuestionAndType($questionId, 'TEST');
                foreach ($answers as $answer) {
                    $this->entityManager->remove($answer);
                }
            }

            $this->entityManager->remove($question);
        }

        $this->entityManager->flush();
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
    }
}

