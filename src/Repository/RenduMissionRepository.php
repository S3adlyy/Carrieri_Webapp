<?php
// src/Repository/RenduMissionRepository.php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RenduMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RenduMission>
 */
class RenduMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RenduMission::class);
    }

    public function findExistingSubmission(int $missionId, int $candidatId): ?RenduMission
    {
        return $this->createQueryBuilder('r')
            ->where('r.missionId = :missionId')
            ->andWhere('r.candidatId = :candidatId')
            ->setParameter('missionId', $missionId)
            ->setParameter('candidatId', $candidatId)
            ->orderBy('r.dateRendu', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RenduMission[]
     */
    public function findByCandidat(int $candidatId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.candidatId = :candidatId')
            ->setParameter('candidatId', $candidatId)
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
    * @return RenduMission[]
    */
    public function findAcceptedSubmissionsByRecruiter(int $recruiterId): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.mission', 'm')
            ->where('r.statut = :statut')
            ->andWhere('m.user = :recruiterId')
            ->setParameter('statut', 'accepte')
            ->setParameter('recruiterId', $recruiterId)
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RenduMission[]
     */
    public function findActiveSessions(int $candidatId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.candidatId = :candidatId')
            ->andWhere('r.statut = :statut')
            ->setParameter('candidatId', $candidatId)
            ->setParameter('statut', 'en_attente')
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array Liste des candidats actifs
     */
    public function findAllActiveCandidates(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Récupérer les sessions actives (celles qui ont commencé il y a moins de 30 min)
        $sql = "
        SELECT DISTINCT 
            r.id as rendu_id,
            r.candidat_id,
            r.mission_id,
            r.date_rendu,
            u.first_name,
            u.last_name,
            u.email,
            m.type as mission_type,
            m.score_min,
            TIMESTAMPDIFF(MINUTE, r.date_rendu, NOW()) as elapsed_minutes
        FROM rendu_mission r
        JOIN user u ON u.id = r.candidat_id
        JOIN mission m ON m.id = r.mission_id
        WHERE r.statut = 'en_attente'
        AND TIMESTAMPDIFF(MINUTE, r.date_rendu, NOW()) < 30
        ORDER BY r.date_rendu DESC
    ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * @return RenduMission[]
     */
    public function findActiveSessionsByMissionIds(array $missionIds): array
    {
        if (empty($missionIds)) {
            return [];
        }

        // Récupérer les sessions avec statut 'en_attente' créées dans les dernières 30 minutes
        $thirtyMinutesAgo = new \DateTime('-30 minutes');

        return $this->createQueryBuilder('r')
            ->where('r.missionId IN (:missionIds)')
            ->andWhere('r.statut = :statut')
            ->andWhere('r.dateRendu >= :thirtyMinutesAgo')
            ->setParameter('missionIds', $missionIds)
            ->setParameter('statut', 'en_attente')
            ->setParameter('thirtyMinutesAgo', $thirtyMinutesAgo)
            ->orderBy('r.dateRendu', 'DESC')
            ->getQuery()
            ->getResult();
    }

}