<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Certification;
use App\Entity\Cours;
use App\Entity\User;
use App\Repository\LeconRepository;
use App\Repository\ModuleRepository;
use App\Repository\ProgressionCoursRepository;
use App\Repository\ProgressionLeconRepository;
use App\Repository\ResultatQuizModuleRepository;
use App\Repository\ResultatTestCoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class CertificateModerationService
{
    private Filesystem $filesystem;

    public function __construct(
        private LeconRepository $leconRepository,
        private ModuleRepository $moduleRepository,
        private ResultatQuizModuleRepository $resultatQuizModuleRepository,
        private ResultatTestCoursRepository $resultatTestCoursRepository,
        private ProgressionCoursRepository $progressionCoursRepository,
        private ProgressionLeconRepository $progressionLeconRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/var/certificate_moderation.json')]
        private string $storagePath,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return array{status:string,reason:string,reviewed_by:string,reviewed_at:string,anomaly:array<string,mixed>}
     */
    public function getCertificateState(Certification $certificate): array
    {
        $default = [
            'status' => 'valid',
            'reason' => '',
            'reviewed_by' => '',
            'reviewed_at' => '',
            'anomaly' => $this->detectLearningInconsistency($certificate),
        ];

        $id = $certificate->getId();
        if ($id === null) {
            return $default;
        }

        $all = $this->readStorage();
        $stored = $all[(string) $id] ?? null;
        if (!is_array($stored)) {
            return $default;
        }

        $status = (string) ($stored['status'] ?? 'valid');
        if (!in_array($status, ['valid', 'invalid'], true)) {
            $status = 'valid';
        }

        return [
            'status' => $status,
            'reason' => (string) ($stored['reason'] ?? ''),
            'reviewed_by' => (string) ($stored['reviewed_by'] ?? ''),
            'reviewed_at' => (string) ($stored['reviewed_at'] ?? ''),
            'anomaly' => $default['anomaly'],
        ];
    }

    public function markValid(Certification $certificate, User $reviewer): void
    {
        $this->upsertState($certificate, 'valid', 'Valide par recruteur', $reviewer);
    }

    public function markInvalid(Certification $certificate, User $reviewer, string $reason = ''): void
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            $cleanReason = 'Certificat invalide (suspicion de fraude).';
        }

        $this->upsertState($certificate, 'invalid', $cleanReason, $reviewer);

        // Réinitialiser la progression du candidat dans ce cours
        $this->resetCourseProgress($certificate);

    }

    public function isInvalid(Certification $certificate): bool
    {
        return $this->getCertificateState($certificate)['status'] === 'invalid';
    }

    /**
     * @return array{is_suspect:bool,risk_level:string,reason:string,details:array<string,mixed>}
     */
    public function detectLearningInconsistency(Certification $certificate): array
    {
        $candidateId = $certificate->getCandidatId();
        $cours = $certificate->getCours();
        $coursId = $certificate->getCoursId();

        if ($candidateId === null || $coursId === null || !$cours instanceof Cours) {
            return [
                'is_suspect' => false,
                'risk_level' => 'low',
                'reason' => 'Donnees insuffisantes pour analyse.',
                'details' => [],
            ];
        }

        $lessons = $this->leconRepository->findByCours($cours);
        $modules = $this->moduleRepository->findByCours($cours);

        $moduleIds = array_values(array_filter(array_map(static fn ($m): ?int => $m->getId(), $modules)));
        $quizResults = $moduleIds === [] ? [] : $this->resultatQuizModuleRepository->findBy([
            'candidatId' => $candidateId,
            'moduleId' => $moduleIds,
        ], ['dateCompletion' => 'ASC', 'id' => 'ASC']);

        $testResults = $this->resultatTestCoursRepository->findBy([
            'candidatId' => $candidateId,
            'coursId' => $coursId,
        ], ['dateCompletion' => 'ASC', 'id' => 'ASC']);

        $firstQuizAt = $quizResults !== [] ? $quizResults[0]->getDateCompletion() : null;
        $bestFinalPercentage = 0.0;
        $finalPassedAt = null;

        foreach ($testResults as $result) {
            $total = max(1, (int) ($result->getTotalPoints() ?? 0));
            $score = max(0, (int) ($result->getScore() ?? 0));
            $percentage = ($score / $total) * 100;
            if ($percentage > $bestFinalPercentage) {
                $bestFinalPercentage = $percentage;
            }

            if ((int) ($result->getReussite() ?? 0) === 1 && $finalPassedAt === null) {
                $finalPassedAt = $result->getDateCompletion();
            }
        }

        if (!$firstQuizAt instanceof \DateTimeInterface || !$finalPassedAt instanceof \DateTimeInterface) {
            return [
                'is_suspect' => false,
                'risk_level' => 'low',
                'reason' => 'Aucune incoherence temporelle detectee.',
                'details' => [
                    'lessons' => count($lessons),
                    'modules' => count($modules),
                    'best_final_percentage' => round($bestFinalPercentage, 1),
                ],
            ];
        }

        $actualMinutes = max(0, (int) floor(($finalPassedAt->getTimestamp() - $firstQuizAt->getTimestamp()) / 60));
        $expectedMinutes = max(2, (int) round((count($lessons) * 1.5) + (count($modules) * 1.0)));

        $suspect = $bestFinalPercentage >= 90.0 && ($actualMinutes <= 2 || $actualMinutes < (int) ceil($expectedMinutes * 0.2));

        return [
            'is_suspect' => $suspect,
            'risk_level' => $suspect ? 'high' : 'low',
            'reason' => $suspect
                ? sprintf('Pattern suspect: progression estimee %d min pour score final %.1f%%.', $actualMinutes, $bestFinalPercentage)
                : 'Aucune incoherence majeure detectee.',
            'details' => [
                'actual_minutes' => $actualMinutes,
                'expected_minutes' => $expectedMinutes,
                'best_final_percentage' => round($bestFinalPercentage, 1),
                'lessons' => count($lessons),
                'modules' => count($modules),
            ],
        ];
    }

    private function upsertState(Certification $certificate, string $status, string $reason, User $reviewer): void
    {
        $id = $certificate->getId();
        if ($id === null) {
            return;
        }

        $all = $this->readStorage();
        $all[(string) $id] = [
            'status' => $status,
            'reason' => $reason,
            'reviewed_by' => trim(((string) $reviewer->getFirstName()) . ' ' . ((string) $reviewer->getLastName())) ?: ((string) $reviewer->getEmail()),
            'reviewed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'candidat_id' => $certificate->getCandidatId(),
            'cours_id' => $certificate->getCoursId(),
            'cours_titre' => (string) ($certificate->getCours()?->getTitre() ?? ''),
            'notified_candidates' => is_array($all[(string) $id]['notified_candidates'] ?? null) ? $all[(string) $id]['notified_candidates'] : [],
        ];

        $this->writeStorage($all);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readStorage(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $raw = file_get_contents($this->storagePath);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function writeStorage(array $data): void
    {
        $this->filesystem->mkdir(dirname($this->storagePath), 0755);
        file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<array{certificate_id:int,course_title:string,reason:string,reviewed_at:string}>
     */
    public function consumePendingFraudPopupsForCandidate(int $candidateId): array
    {
        if ($candidateId <= 0) {
            return [];
        }

        $all = $this->readStorage();
        $alerts = [];
        $changed = false;
        $candidateKey = (string) $candidateId;

        foreach ($all as $certificateId => $state) {
            if (!is_array($state)) {
                continue;
            }

            if ((string) ($state['status'] ?? 'valid') !== 'invalid') {
                continue;
            }

            if ((int) ($state['candidat_id'] ?? 0) !== $candidateId) {
                continue;
            }

            $notified = is_array($state['notified_candidates'] ?? null) ? $state['notified_candidates'] : [];
            if (isset($notified[$candidateKey])) {
                continue;
            }

            $alerts[] = [
                'certificate_id' => (int) $certificateId,
                'course_title' => (string) ($state['cours_titre'] ?? 'Cours'),
                'reason' => (string) ($state['reason'] ?? 'Fraude signalee par recruteur.'),
                'reviewed_at' => (string) ($state['reviewed_at'] ?? ''),
            ];

            $notified[$candidateKey] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $state['notified_candidates'] = $notified;
            $all[(string) $certificateId] = $state;
            $changed = true;
        }

        if ($changed) {
            $this->writeStorage($all);
        }

        return $alerts;
    }

    /**
     * Réinitialise la progression du candidat dans un cours (fraude détectée).
     * Utilisable aussi pour un backfill des certificats frauduleux historiques.
     */
    public function resetCourseProgress(Certification $certificate): void
    {
        $candidatId = $certificate->getCandidatId();
        $coursId = $certificate->getCoursId();

        if ($candidatId === null || $coursId === null) {
            return;
        }

        // Réinitialiser la progression du cours à 0
        $progressionCours = $this->progressionCoursRepository->findOneBy([
            'candidatId' => $candidatId,
            'coursId' => $coursId,
        ]);

        if ($progressionCours !== null) {
            $progressionCours->setProgression(0);
            $progressionCours->setDateMaj(new \DateTimeImmutable());
            $this->entityManager->persist($progressionCours);
        }

        // Supprimer toutes les progressions de leçons pour ce cours
        $cours = $certificate->getCours();
        if ($cours !== null) {
            $modules = $this->moduleRepository->findByCours($cours);
            $moduleIds = [];
            foreach ($modules as $module) {
                $moduleId = $module->getId();
                if ($moduleId !== null) {
                    $moduleIds[] = $moduleId;
                }
            }

            if ($moduleIds !== []) {
                $lecons = $this->leconRepository->findByModuleIds($moduleIds);
                foreach ($lecons as $lecon) {
                    $leconId = $lecon->getId();
                    if ($leconId === null) {
                        continue;
                    }

                    $progressionLecon = $this->progressionLeconRepository->findOneBy([
                        'candidatId' => $candidatId,
                        'leconId' => $leconId,
                    ]);

                    if ($progressionLecon !== null) {
                        $this->entityManager->remove($progressionLecon);
                    }
                }
            }
        }

        // Supprimer tous les résultats de quiz modules
        $quizResults = $this->resultatQuizModuleRepository->findBy([
            'candidatId' => $candidatId,
        ]);

        foreach ($quizResults as $quizResult) {
            $module = $quizResult->getModule();
            if ($module !== null && $module->getCours()?->getId() === $coursId) {
                $this->entityManager->remove($quizResult);
            }
        }

        // Supprimer tous les résultats de tests finaux
        $testResults = $this->resultatTestCoursRepository->findBy([
            'candidatId' => $candidatId,
            'coursId' => $coursId,
        ]);

        foreach ($testResults as $testResult) {
            $this->entityManager->remove($testResult);
        }

        // Persister les changements
        $this->entityManager->flush();
    }
}






