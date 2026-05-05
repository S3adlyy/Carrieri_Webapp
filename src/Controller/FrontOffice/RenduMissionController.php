<?php
// src/Controller/FrontOffice/RenduMissionController.php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\RenduMission;
use App\Entity\Mission;
use App\Entity\User;
use App\Repository\MissionRepository;
use App\Repository\RenduMissionRepository;
use App\Service\AiCodeEvaluatorService;
use App\Service\MissionAnalyzerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/candidat/rendu-mission')]
#[IsGranted('ROLE_CANDIDAT')]
class RenduMissionController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionRepository $missionRepository,
        private RenduMissionRepository $renduMissionRepository,
        private AiCodeEvaluatorService $aiEvaluator,
        private MissionAnalyzerService $missionAnalyzer,
    ) {}

    #[Route('/mes-resultats', name: 'app_candidate_my_results')]
    public function myResults(): Response
    {
        $user = $this->requireUser();
        $soumissions = $this->renduMissionRepository->findBy(
            ['candidatId' => $user->getId()],
            ['dateRendu' => 'DESC']
        );

        return $this->render('FrontOffice/main/my_results.html.twig', [
            'soumissions' => $soumissions,
        ]);
    }

    #[Route('/status/{id}', name: 'app_candidate_rendu_status')]
    public function status(int $id): Response
    {
        $user = $this->requireUser();
        $rendu = $this->renduMissionRepository->find($id);
        if (!$rendu || $rendu->getUser() !== $user) {
            throw $this->createNotFoundException('Soumission non trouvée');
        }

        return $this->render('FrontOffice/main/rendu_status.html.twig', [
            'rendu'   => $rendu,
            'mission' => $rendu->getMission(),
        ]);
    }

    #[Route('/{id}', name: 'app_candidate_rendu_mission')]
    public function index(int $id, Request $request, SessionInterface $session): Response
    {
        $user = $this->requireUser();
        $mission = $this->missionRepository->find($id)
            ?? throw $this->createNotFoundException('Mission non trouvée');

        // Vérifier si une soumission existe déjà (terminée)
        $missionId = $mission->getId();
        $userId = $user->getId();
        if ($missionId === null || $userId === null) {
            throw $this->createAccessDeniedException('Identifiants de mission ou utilisateur manquants');
        }

        $existingRendu = $this->renduMissionRepository->findExistingSubmission(
            $missionId,
            $userId
        );

        if ($existingRendu && $existingRendu->getStatut() !== 'en_attente') {
            $this->addFlash('warning', 'Vous avez déjà soumis cette mission.');
            return $this->redirectToRoute('app_candidate_my_results');
        }

        // CRÉER OU RÉCUPÉRER LA SESSION ACTIVE
        $sessionKey = "mission_{$id}_start_time";
        $executionsKey = "mission_{$id}_executions";
        $renduIdKey = "mission_{$id}_rendu_id";

        // Vérifier si une session active existe déjà en base
        $activeRendu = null;
        if ($session->has($renduIdKey)) {
            $activeRendu = $this->renduMissionRepository->find($session->get($renduIdKey));
        }

        if (!$activeRendu && $existingRendu && $existingRendu->getStatut() === 'en_attente') {
            $activeRendu = $existingRendu;
        }

        if (!$activeRendu) {
            // Créer un nouvel enregistrement en "en_attente"
            $activeRendu = new RenduMission();
            $activeRendu->setCodeSolution(''); // Code vide au début
            $activeRendu->setLangue('python');
            $activeRendu->setDateRendu(new \DateTime());
            $activeRendu->setScore(null);
            $activeRendu->setResultat(null);
            $activeRendu->setFeedback(null);
            $activeRendu->setStatut('en_attente');
            $activeRendu->setMission($mission);
            $activeRendu->setUser($user);

            $this->entityManager->persist($activeRendu);
            $this->entityManager->flush();

            $session->set($renduIdKey, $activeRendu->getId());
        }

        if (!$session->has($sessionKey)) {
            $session->set($sessionKey, time());
            $session->set($executionsKey, 3);
            $session->set("mission_{$id}_token", bin2hex(random_bytes(32)));
        }

        $startTime = $session->get($sessionKey);
        $timeRemaining = 1800 - (time() - $startTime);

        if ($timeRemaining <= 0) {
            return $this->autoSubmitWithZero($mission, $user, $session, "Temps écoulé (30 minutes)");
        }

        // Analyser la mission
        $cacheKey = "mission_data_{$id}";
        if (!$session->has($cacheKey)) {
            $missionData = $this->missionAnalyzer->analyzeMissionDescription(
                $mission->getDescription() ?? '',
                $mission->getType() ?? ''
            );
            $session->set($cacheKey, $missionData);
        } else {
            $missionData = $session->get($cacheKey);
        }

        return $this->render('FrontOffice/main/rendu_mission.html.twig', [
            'mission' => $mission,
            'existingRendu' => $existingRendu,
            'activeRendu' => $activeRendu,
            'remainingExecutions' => $session->get($executionsKey, 3),
            'timeRemaining' => $timeRemaining,
            'sessionToken' => $session->get("mission_{$id}_token"),
            'missionData' => $missionData
        ]);
    }

    #[Route('/execute/{id}', name: 'app_candidate_execute_code', methods: ['POST'])]
    public function executeCode(int $id, Request $request, SessionInterface $session): JsonResponse
    {
        $user = $this->requireUser();
        $mission = $this->missionRepository->find($id);

        if (!$mission) {
            return new JsonResponse(['success' => false, 'error' => 'Mission non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $sessionToken = $session->get("mission_{$id}_token");

        if ($token !== $sessionToken) {
            return new JsonResponse(['success' => false, 'error' => 'Session invalide'], 400);
        }

        // Vérifier les exécutions restantes
        $executionsKey = "mission_{$id}_executions";
        $remaining = $session->get($executionsKey, 0);

        if ($remaining <= 0) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Vous avez atteint la limite de 3 exécutions',
                'limitReached' => true
            ], 403);
        }

        // Décrémenter le compteur
        $session->set($executionsKey, $remaining - 1);

        // Exécution du code (simulation)
        $code = $data['code'] ?? '';
        $language = $data['language'] ?? 'python';

        // Appel à votre service d'exécution
        $result = $this->executeUserCode($code, $language);

        return new JsonResponse([
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => null,
            'remainingExecutions' => $session->get($executionsKey)
        ]);
    }

    #[Route('/auto-submit/{id}', name: 'app_candidate_auto_submit', methods: ['POST'])]
    public function autoSubmit(int $id, Request $request, SessionInterface $session): JsonResponse
    {
        $user = $this->requireUser();
        $mission = $this->missionRepository->find($id);

        if (!$mission) {
            return new JsonResponse(['success' => false, 'error' => 'Mission non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $reason = $data['reason'] ?? 'Page quittée';
        $code = $data['code'] ?? '';
        $language = $data['language'] ?? 'javascript';

        $sessionToken = $session->get("mission_{$id}_token");

        if ($token !== $sessionToken) {
            return new JsonResponse(['success' => false, 'error' => 'Session invalide'], 400);
        }

        // Vérifier si déjà soumis
        $existingRendu = $this->renduMissionRepository->findExistingSubmission(
            $mission->getId(),
            $user->getId()
        );

        if ($existingRendu) {
            return new JsonResponse(['success' => false, 'error' => 'Déjà soumis'], 400);
        }

        // Créer la soumission avec score 0
        $renduMission = new RenduMission();
        $renduMission->setCodeSolution($code);
        $renduMission->setLangue($language);
        $renduMission->setDateRendu(new \DateTime());
        $renduMission->setScore(0);
        $renduMission->setResultat($this->generateZeroScoreHtml($reason));
        $renduMission->setFeedback("Session interrompue : $reason. Score: 0/100");
        $renduMission->setStatut('refuse');
        $renduMission->setMission($mission);
        $renduMission->setUser($user);

        $this->entityManager->persist($renduMission);
        $this->entityManager->flush();

        // Nettoyer la session
        $session->remove("mission_{$id}_start_time");
        $session->remove("mission_{$id}_executions");
        $session->remove("mission_{$id}_token");

        return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_candidate_my_results')]);
    }

    #[Route('/submit/{id}', name: 'app_candidate_submit', methods: ['POST'])]
    public function submit(int $id, Request $request, SessionInterface $session): JsonResponse
    {
        $user = $this->requireUser();
        $mission = $this->missionRepository->find($id);

        if (!$mission) {
            return new JsonResponse(['success' => false, 'error' => 'Mission non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';
        $langue = $data['language'] ?? 'javascript';
        $token = $data['token'] ?? null;

        $sessionToken = $session->get("mission_{$id}_token");

        if ($token !== $sessionToken) {
            return new JsonResponse(['success' => false, 'error' => 'Session invalide'], 400);
        }

        // Récupérer le rendu actif
        $renduIdKey = "mission_{$id}_rendu_id";
        $activeRendu = null;

        if ($session->has($renduIdKey)) {
            $activeRendu = $this->renduMissionRepository->find($session->get($renduIdKey));
        }

        if (!$activeRendu) {
            $missionId = $mission->getId();
            $userId = $user->getId();
            if ($missionId === null || $userId === null) {
                return new JsonResponse(['success' => false, 'error' => 'Identifiants manquants'], 500);
            }

            $activeRendu = $this->renduMissionRepository->findExistingSubmission(
                $missionId,
                $userId
            );
        }

        if (!$activeRendu) {
            $activeRendu = new RenduMission();
            $activeRendu->setMission($mission);
            $activeRendu->setUser($user);
        }

        // Appel à l'API d'évaluation IA
        $evaluation = $this->aiEvaluator->evaluate(
            code: $code,
            language: $langue,
            missionDescription: $mission->getDescription() ?? '',
            missionTitle: $mission->getType() ?? 'Mission #' . $mission->getId(),
            scoreMin: $mission->getScoreMin() ?? 60,
        );

        // Mettre à jour le rendu
        $activeRendu->setCodeSolution($code);
        $activeRendu->setLangue($langue);
        $activeRendu->setDateRendu(new \DateTime());
        $activeRendu->setScore($evaluation['score']);
        $activeRendu->setResultat($evaluation['resultat_html']);
        $activeRendu->setFeedback($evaluation['feedback']);
        $activeRendu->setStatut($evaluation['statut']);

        $this->entityManager->persist($activeRendu);
        $this->entityManager->flush();

        // Nettoyer la session
        $session->remove("mission_{$id}_start_time");
        $session->remove("mission_{$id}_executions");
        $session->remove("mission_{$id}_token");
        $session->remove("mission_{$id}_rendu_id");

        return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_candidate_rendu_status', ['id' => $activeRendu->getId()])]);
    }

    private function autoSubmitWithZero(Mission $mission, User $user, SessionInterface $session, string $reason): Response
    {
        // Récupérer le rendu actif
        $renduIdKey = "mission_{$mission->getId()}_rendu_id";
        $activeRendu = null;

        if ($session->has($renduIdKey)) {
            $activeRendu = $this->renduMissionRepository->find($session->get($renduIdKey));
        }

        if (!$activeRendu) {
            $missionId = $mission->getId();
            $userId = $user->getId();
            if ($missionId === null || $userId === null) {
                return new JsonResponse(['success' => false, 'error' => 'Identifiants manquants'], 500);
            }

            $activeRendu = $this->renduMissionRepository->findExistingSubmission(
                $missionId,
                $userId
            );
        }

        if (!$activeRendu) {
            $activeRendu = new RenduMission();
            $activeRendu->setMission($mission);
            $activeRendu->setUser($user);
        }

        $activeRendu->setCodeSolution('');
        $activeRendu->setLangue('');
        $activeRendu->setDateRendu(new \DateTime());
        $activeRendu->setScore(0);
        $activeRendu->setResultat($this->generateZeroScoreHtml($reason));
        $activeRendu->setFeedback("Session expirée : $reason. Score: 0/100");
        $activeRendu->setStatut('refuse');

        $this->entityManager->persist($activeRendu);
        $this->entityManager->flush();

        // Nettoyer la session
        $missionId = $mission->getId();
        $session->remove("mission_{$missionId}_start_time");
        $session->remove("mission_{$missionId}_executions");
        $session->remove("mission_{$missionId}_token");
        $session->remove("mission_{$missionId}_rendu_id");

        $this->addFlash('warning', "Session expirée : $reason. Score: 0/100");
        return $this->redirectToRoute('app_candidate_my_results');
    }

    /**
     * @return array{success: bool, output: string}
     */
    private function executeUserCode(string $code, string $language): array
    {
        // Implémentez votre logique d'exécution ici
        // Exemple simple :
        return [
            'success' => true,
            'output' => "Code exécuté avec succès en $language\nLongueur: " . strlen($code) . " caractères"
        ];
    }

    private function generateZeroScoreHtml(string $reason): string
    {
        return '
        <div class="ai-evaluation" style="font-family:sans-serif">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                <div style="font-size:2rem;font-weight:bold;color:#dc3545">0%</div>
                <div>
                    <span style="background:#dc3545;color:#fff;padding:3px 10px;border-radius:12px">✗ REFUSÉ</span>
                    <div style="font-size:.8rem;color:#666;margin-top:3px">0/5 critères validés</div>
                </div>
            </div>
            <div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:8px;padding:15px;margin-top:10px">
                <strong style="color:#721c24">⚠️ Session interrompue</strong>
                <p style="margin-top:8px;color:#721c24">Raison : ' . htmlspecialchars($reason) . '</p>
                <p style="margin-top:8px;font-size:.85rem;color:#721c24">Score automatique : 0/100</p>
            </div>
            <p style="font-size:.75rem;color:#888;margin-top:8px">⚡ Évalué par IA – Carrieri Platform</p>
        </div>';
    }

    #[Route('/live-sessions', name: 'app_candidate_live_sessions')]
    public function liveSessions(): Response
    {
        $user = $this->requireUser();

        // Récupérer les missions actives du candidat
        $userId = $user->getId();
        $activeMissions = $userId === null ? [] : $this->renduMissionRepository->findActiveSessions($userId);

        return $this->render('FrontOffice/main/live_sessions.html.twig', [
            'missions' => $activeMissions,
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }
}
