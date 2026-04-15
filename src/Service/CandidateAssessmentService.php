<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cours;
use App\Entity\Module;
use App\Entity\ResultatQuizModule;
use App\Entity\ResultatTestCours;
use App\Entity\User;
use App\Repository\QuestionQuizRepository;
use App\Repository\QuestionTestRepository;
use App\Repository\ReponseRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CandidateAssessmentService
{
    private const QUIZ_PASS_THRESHOLD = 70.0;
    private const QUIZ_EXPECTED_QUESTIONS = 5;
    private const FINAL_TEST_EXPECTED_QUESTIONS = 15;

    public function __construct(
        private QuestionQuizRepository $questionQuizRepository,
        private QuestionTestRepository $questionTestRepository,
        private ReponseRepository $reponseRepository,
        private AssessmentAutoGeneratorService $assessmentAutoGeneratorService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{questions: list<array{id:int,text:string,points:int,answers:list<array{id:int,text:string,is_correct:bool>}>>, expected_count:int}
     */
    public function buildModuleQuiz(Module $module): array
    {
        $moduleId = $module->getId();
        if ($moduleId === null) {
            return ['questions' => [], 'expected_count' => self::QUIZ_EXPECTED_QUESTIONS];
        }

        $this->assessmentAutoGeneratorService->ensureModuleQuizGenerated($module, self::QUIZ_EXPECTED_QUESTIONS);

        $questions = $this->questionQuizRepository->findByModuleOrdered($moduleId, self::QUIZ_EXPECTED_QUESTIONS);

        return [
            'questions' => $this->hydrateQuestions($questions, 'QUIZ'),
            'expected_count' => self::QUIZ_EXPECTED_QUESTIONS,
        ];
    }

    /**
     * @return array{questions: list<array{id:int,text:string,points:int,answers:list<array{id:int,text:string,is_correct:bool>}>>, expected_count:int}
     */
    public function buildCoursFinalTest(Cours $cours): array
    {
        $coursId = $cours->getId();
        if ($coursId === null) {
            return ['questions' => [], 'expected_count' => self::FINAL_TEST_EXPECTED_QUESTIONS];
        }

        $this->assessmentAutoGeneratorService->ensureCoursFinalTestGenerated($cours, self::FINAL_TEST_EXPECTED_QUESTIONS);

        $questions = $this->questionTestRepository->findByCoursOrdered($coursId, self::FINAL_TEST_EXPECTED_QUESTIONS);

        return [
            'questions' => $this->hydrateQuestions($questions, 'TEST'),
            'expected_count' => self::FINAL_TEST_EXPECTED_QUESTIONS,
        ];
    }

    /**
     * @param list<array{id:int,text:string,points:int,answers:list<array{id:int,text:string,is_correct:bool>}>> $questions
     * @param array<string, mixed> $submittedAnswers
     * @return array{score:int,total_points:int,percentage:float,passed:bool,missing_question_ids:list<int>}
     */
    public function evaluate(array $questions, array $submittedAnswers): array
    {
        $score = 0;
        $totalPoints = 0;
        $missing = [];

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $totalPoints += $question['points'];

            $selectedAnswerId = (int) ($submittedAnswers[(string) $questionId] ?? 0);
            if ($selectedAnswerId <= 0) {
                $missing[] = $questionId;
                continue;
            }

            foreach ($question['answers'] as $answer) {
                if ($answer['id'] === $selectedAnswerId && $answer['is_correct']) {
                    $score += $question['points'];
                    break;
                }
            }
        }

        $percentage = $totalPoints > 0 ? round(($score / $totalPoints) * 100, 2) : 0.0;

        return [
            'score' => $score,
            'total_points' => $totalPoints,
            'percentage' => $percentage,
            'passed' => $percentage >= self::QUIZ_PASS_THRESHOLD,
            'missing_question_ids' => $missing,
        ];
    }

    /**
     * @param array{score:int,total_points:int,passed:bool} $result
     */
    public function saveModuleResult(User $user, Module $module, array $result): ResultatQuizModule
    {
        $entity = (new ResultatQuizModule())
            ->setCandidatId($user->getId())
            ->setModuleId($module->getId())
            ->setScore($result['score'])
            ->setTotalPoints($result['total_points'])
            ->setReussite($result['passed'] ? 1 : 0)
            ->setDateCompletion(new \DateTimeImmutable())
            ->setUser($user)
            ->setModule($module);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param array{score:int,total_points:int,passed:bool} $result
     */
    public function saveCoursResult(User $user, Cours $cours, array $result): ResultatTestCours
    {
        $entity = (new ResultatTestCours())
            ->setCandidatId($user->getId())
            ->setCoursId($cours->getId())
            ->setScore($result['score'])
            ->setTotalPoints($result['total_points'])
            ->setReussite($result['passed'] ? 1 : 0)
            ->setDateCompletion(new \DateTimeImmutable())
            ->setUser($user)
            ->setCours($cours);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param list<object> $questionEntities
     * @return list<array{id:int,text:string,points:int,answers:list<array{id:int,text:string,is_correct:bool>}>>
     */
    private function hydrateQuestions(array $questionEntities, string $questionType): array
    {
        $out = [];

        foreach ($questionEntities as $question) {
            $questionId = $question->getId();
            if ($questionId === null) {
                continue;
            }

            $answers = $this->reponseRepository->findByQuestionAndType($questionId, $questionType);
            if ($answers === []) {
                continue;
            }

            $hydratedAnswers = [];
            foreach ($answers as $answer) {
                $answerId = $answer->getId();
                if ($answerId === null) {
                    continue;
                }

                $hydratedAnswers[] = [
                    'id' => $answerId,
                    'text' => (string) $answer->getReponseText(),
                    'is_correct' => (int) $answer->getEstCorrecte() === 1,
                ];
            }

            if ($hydratedAnswers === []) {
                continue;
            }

            $out[] = [
                'id' => $questionId,
                'text' => (string) $question->getQuestionText(),
                'points' => (int) ($question->getPoints() ?? 1),
                'answers' => $hydratedAnswers,
            ];
        }

        return $out;
    }
}

