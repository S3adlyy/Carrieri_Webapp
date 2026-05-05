<?php

namespace App\Controller;

use App\Entity\AiInterviewSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai-interview')]
class AiInterviewController extends AbstractController
{
    // Page d'accueil pour le candidat
    #[Route('/', name: 'app_ai_interview_index')]
    public function index(): Response
    {
        return $this->render('FrontOffice/ai_interview/index.html.twig');
    }

    #[Route('/interview-vosk', name: 'app_ai_interview_vosk')]
    public function interviewVosk(): Response
    {
        return $this->render('FrontOffice/ai_interview/interview_vosk.html.twig');
    }

    // Interface de l'entretien
    #[Route('/start', name: 'app_ai_interview_start')]
    public function start(): Response
    {
        return $this->render('FrontOffice/ai_interview/interview.html.twig');
    }

    #[Route('/interview-v2', name: 'app_ai_interview_v2')]
    public function interviewV2(): Response
    {
        return $this->render('FrontOffice/ai_interview/interview_v2.html.twig');
    }

    // API: Démarrer une session
    #[Route('/api/session/start', name: 'api_ai_interview_session_start', methods: ['POST'])]
    public function startSession(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Questions prédéfinies
        $questions = [
            [
                'id' => 1,
                'text' => 'Bonjour ! Pour commencer, pourriez-vous vous présenter et me parler de votre parcours professionnel ?',
                'keywords' => ['expérience', 'formation', 'compétence', 'parcours', 'diplôme', 'carrière'],
                'maxDuration' => 90
            ],
            [
                'id' => 2,
                'text' => 'Quelles sont vos principales compétences techniques et comment les avez-vous développées ?',
                'keywords' => ['technique', 'compétence', 'projet', 'formation', 'apprentissage', 'maîtrise'],
                'maxDuration' => 90
            ],
            [
                'id' => 3,
                'text' => 'Pouvez-vous me décrire une situation difficile que vous avez rencontrée et comment vous l\'avez résolue ?',
                'keywords' => ['difficulté', 'problème', 'solution', 'défi', 'résolution', 'crise'],
                'maxDuration' => 120
            ],
            [
                'id' => 4,
                'text' => 'Pourquoi souhaitez-vous rejoindre notre entreprise et qu\'est-ce qui vous motive ?',
                'keywords' => ['entreprise', 'motivation', 'valeur', 'projet', 'mission', 'objectif'],
                'maxDuration' => 90
            ],
            [
                'id' => 5,
                'text' => 'Où vous voyez-vous professionnellement dans les 5 prochaines années ?',
                'keywords' => ['futur', 'objectif', 'évolution', 'carrière', 'ambition', 'progression'],
                'maxDuration' => 90
            ],
            [
                'id' => 6,
                'text' => 'Quel est selon vous votre plus grand accomplissement professionnel ?',
                'keywords' => ['accomplissement', 'réussite', 'projet', 'réalisation', 'fierté', 'impact'],
                'maxDuration' => 90
            ]
        ];

        $session = new AiInterviewSession();
        $session->setCandidateName($data['name'] ?? 'Candidat');
        $session->setCandidateEmail($data['email'] ?? 'candidat@email.com');
        $session->setPosition($data['position'] ?? 'Développeur');
        $session->setStartedAt(new \DateTime());
        $session->setQuestions($questions);
        $session->setStatus('en_cours');

        $em->persist($session);
        $em->flush();

        return $this->json([
            'success' => true,
            'sessionId' => $session->getId(),
            'questions' => $questions
        ]);
    }

    // API: Évaluer une réponse
    #[Route('/api/session/evaluate', name: 'api_ai_interview_evaluate', methods: ['POST'])]
    public function evaluateResponse(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $session = $em->getRepository(AiInterviewSession::class)->find($data['sessionId']);

        if (!$session) {
            return $this->json(['success' => false, 'error' => 'Session non trouvée'], 404);
        }

        $questionId = $data['questionId'];
        $response = $data['response'];
        $questions = $session->getQuestions();
        $question = $questions[$questionId - 1];

        // Évaluation de la réponse
        $evaluation = $this->evaluateAnswer($response, $question);

        // Sauvegarder la réponse
        $responses = $session->getResponses();
        $responses[] = [
            'questionId' => $questionId,
            'question' => $question['text'],
            'response' => $response,
            'evaluation' => $evaluation,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        $session->setResponses($responses);

        // Sauvegarder l'évaluation
        $evaluations = $session->getEvaluations();
        $evaluations[] = $evaluation;
        $session->setEvaluations($evaluations);

        // Calculer le score moyen
        $totalScore = array_sum(array_column($evaluations, 'score'));
        $session->setFinalScore($totalScore / count($evaluations));

        $em->flush();

        return $this->json([
            'success' => true,
            'evaluation' => $evaluation,
            'nextQuestionId' => $questionId + 1,
            'isLast' => $questionId >= count($questions)
        ]);
    }

    // API: Terminer l'entretien
    #[Route('/api/session/complete/{id}', name: 'api_ai_interview_complete', methods: ['POST'])]
    public function completeSession(AiInterviewSession $session, EntityManagerInterface $em): JsonResponse
    {
        $session->setEndedAt(new \DateTime());
        $session->setStatus('termine');
        $session->setGlobalFeedback($this->generateFeedback($session));

        $em->flush();

        return $this->json([
            'success' => true,
            'finalScore' => $session->getFinalScore(),
            'feedback' => $session->getGlobalFeedback()
        ]);
    }

    // API: Obtenir les résultats
    #[Route('/api/session/result/{id}', name: 'api_ai_interview_result', methods: ['GET'])]
    public function getResult(AiInterviewSession $session): JsonResponse
    {
        return $this->json([
            'success' => true,
            'candidateName' => $session->getCandidateName(),
            'position' => $session->getPosition(),
            'finalScore' => round($session->getFinalScore(), 1),
            'responses' => $session->getResponses(),
            'feedback' => $session->getGlobalFeedback()
        ]);
    }

    #[Route('/interview', name: 'app_ai_interview_interface')]
    public function interviewInterface(): Response
    {
        return $this->render('FrontOffice/ai_interview/interview.html.twig');
    }

    #[Route('/api/session/{id}', name: 'api_ai_interview_session_get', methods: ['GET'])]
    public function getSession(AiInterviewSession $session): JsonResponse
    {
        return $this->json([
            'success' => true,
            'questions' => $session->getQuestions(),
            'status' => $session->getStatus()
        ]);
    }


    // Méthode d'évaluation
    /**
     * @param array{id:int, text:string, keywords:list<string>, maxDuration:int} $question
     * @return array{
     *     score: float,
     *     grade: string,
     *     feedback: string,
     *     wordCount: int,
     *     keywordsFound: list<string>,
     *     details: array{keywordScore: float, lengthScore: int, structureScore: float, confidenceScore: int}
     * }
     */
    private function evaluateAnswer(string $response, array $question): array
    {
        $responseLower = strtolower($response);
        $keywords = $question['keywords'];

        // Score basé sur les mots-clés
        $foundKeywords = [];
        foreach ($keywords as $keyword) {
            if (str_contains($responseLower, $keyword)) {
                $foundKeywords[] = $keyword;
            }
        }
        $keywordScore = count($foundKeywords) / count($keywords) * 40;

        // Score basé sur la longueur (mots)
        $wordCount = str_word_count($response);
        if ($wordCount < 30) {
            $lengthScore = 10;
        } elseif ($wordCount < 60) {
            $lengthScore = 20;
        } elseif ($wordCount < 120) {
            $lengthScore = 30;
        } else {
            $lengthScore = 30;
        }

        // Score basé sur la structure (mots de liaison)
        $structureWords = ['premièrement', 'deuxièmement', 'ensuite', 'finalement', 'car', 'donc', 'par exemple', 'notamment', 'cependant'];
        $structureCount = 0;
        foreach ($structureWords as $word) {
            if (str_contains($responseLower, $word)) {
                $structureCount++;
            }
        }
        $structureScore = min($structureCount / 3 * 15, 15);

        // Score de confiance (absence d'hésitations)
        $hesitationWords = ['euh', 'hum', 'ah', 'ben', 'je sais pas', 'jsp', 'peut-être'];
        $hesitationCount = 0;
        foreach ($hesitationWords as $word) {
            $hesitationCount += substr_count($responseLower, $word);
        }
        $confidenceScore = max(0, 15 - ($hesitationCount * 3));

        // Score total
        $totalScore = $keywordScore + $lengthScore + $structureScore + $confidenceScore;

        // Feedback
        if ($totalScore >= 80) {
            $grade = 'Excellent';
            $feedback = 'Réponse excellente ! Vous avez couvert tous les points importants avec structure et pertinence.';
        } elseif ($totalScore >= 60) {
            $grade = 'Bon';
            $feedback = 'Bonne réponse ! Quelques points pourraient être approfondis.';
        } elseif ($totalScore >= 40) {
            $grade = 'Moyen';
            $feedback = 'Réponse correcte mais manque de détails ou de structure.';
        } else {
            $grade = 'À améliorer';
            $feedback = 'Réponse trop courte ou hors sujet. Prenez plus de temps pour développer.';
        }

        return [
            'score' => round($totalScore, 1),
            'grade' => $grade,
            'feedback' => $feedback,
            'wordCount' => $wordCount,
            'keywordsFound' => $foundKeywords,
            'details' => [
                'keywordScore' => round($keywordScore, 1),
                'lengthScore' => $lengthScore,
                'structureScore' => $structureScore,
                'confidenceScore' => $confidenceScore
            ]
        ];
    }

    // Générer le feedback final
    private function generateFeedback(AiInterviewSession $session): string
    {
        $score = $session->getFinalScore();
        $responses = $session->getResponses();

        $feedback = "# Bilan de l'entretien IA\n\n";

        if ($score >= 70) {
            $feedback .= "## 🎉 Félicitations !\n\n";
            $feedback .= "Vous avez réalisé un excellent entretien avec un score de **" . round($score, 1) . "%**.\n\n";
            $feedback .= "Vos réponses étaient claires, structurées et pertinentes. Vous démontrez une bonne maîtrise des sujets abordés.\n\n";
        } elseif ($score >= 50) {
            $feedback .= "## 👍 Bon travail\n\n";
            $feedback .= "Vous obtenez un score de **" . round($score, 1) . "%**. C'est un bon résultat !\n\n";
            $feedback .= "Quelques axes d'amélioration pourraient vous aider à atteindre l'excellence.\n\n";
        } else {
            $feedback .= "## 📈 Encouragements\n\n";
            $feedback .= "Vous obtenez un score de **" . round($score, 1) . "%**. Ne vous découragez pas !\n\n";
            $feedback .= "Avec plus de préparation, vous pouvez considérablement améliorer vos résultats.\n\n";
        }

        $feedback .= "### Points forts identifiés :\n";
        $feedback .= "- Capacité à répondre aux questions\n";
        $feedback .= "- Expression orale\n\n";

        $feedback .= "### Axes d'amélioration :\n";
        $feedback .= "- Développez vos réponses avec des exemples concrets\n";
        $feedback .= "- Structurez mieux vos réponses (introduction, développement, conclusion)\n";
        $feedback .= "- Utilisez des mots-clés techniques liés au poste\n\n";

        $feedback .= "### Détail des réponses :\n";
        foreach ($responses as $i => $resp) {
            $feedback .= sprintf(
                "\n**Question %d:** %s\n\n**Votre réponse:** %s\n\n**Score:** %.1f%%\n\n---\n",
                $i + 1,
                substr($resp['question'], 0, 100),
                substr($resp['response'], 0, 200) . (strlen($resp['response']) > 200 ? '...' : ''),
                $resp['evaluation']['score']
            );
        }

        return $feedback;
    }
}
