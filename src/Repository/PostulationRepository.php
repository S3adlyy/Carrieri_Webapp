<?php

// src/Repository/PostulationRepository.php

namespace App\Repository;

use App\Entity\OffreEmploi;
use App\Entity\Postulation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Postulation>
 */
class PostulationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Postulation::class);
    }

    public function hasUserAppliedToOffer(User $user, OffreEmploi $offre): bool
    {
        return $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.user = :user')
                ->andWhere('p.offreEmploi = :offre')
                ->setParameter('user', $user)
                ->setParameter('offre', $offre)
                ->getQuery()
                ->getSingleScalarResult() > 0;
    }

    /**
     * @param list<int> $offerIds
     * @return array<int, int>
     */
    public function countByOfferIds(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.offreEmploi) AS offreId, COUNT(p.id) AS total')
            ->andWhere('p.offreEmploi IN (:offerIds)')
            ->setParameter('offerIds', $offerIds)
            ->groupBy('p.offreEmploi')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['offreId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return Postulation[]
     */
    public function findByCandidate(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.datePostulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function getStatsByUser(User $user): array
    {
        return [
            'total' => $this->count(['user' => $user]),
            'accepted' => $this->count(['user' => $user, 'statut' => 'Acceptée']),
            'refused' => $this->count(['user' => $user, 'statut' => 'Refusée']),
            'pending' => $this->count(['user' => $user, 'statut' => 'En attente']),
        ];
    }

    /**
     * @return Postulation[]
     */
    public function findByRecruiter(User $recruiter): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->leftJoin('p.user', 'c')
            ->addSelect('c')
            ->andWhere('o.user = :recruiter')
            ->setParameter('recruiter', $recruiter)
            ->orderBy('p.datePostulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Postulation[]
     */
    public function findByOffreAndRecruiter(int $offreId, User $recruiter): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->leftJoin('p.user', 'c')
            ->addSelect('c')
            ->andWhere('o.user = :recruiter')
            ->andWhere('p.offreEmploi = :offreId')
            ->setParameter('recruiter', $recruiter)
            ->setParameter('offreId', $offreId)
            ->orderBy('p.datePostulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Advanced search for Recruiter Dashboard (kept as-is)
     * @param array<string, mixed> $filters
     * @return Postulation[]
     */
    public function searchPostulationsWithFiltersForRecruiter(User $recruiter, array $filters = []): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $recruiter->getRoles());

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->leftJoin('p.user', 'c')
            ->addSelect('c')
            ->orderBy('p.datePostulation', 'DESC');

        if (!$isAdmin) {
            $qb->andWhere('o.user = :recruiter')
                ->setParameter('recruiter', $recruiter);
        }

        // Filtre par mot-clé
        if (!empty($filters['keyword'])) {
            $qb->andWhere('
                c.firstName LIKE :keyword 
                OR c.lastName LIKE :keyword 
                OR c.email LIKE :keyword 
                OR o.titre LIKE :keyword 
                OR o.entreprise LIKE :keyword
                OR p.motivationCandidature LIKE :keyword
            ')
                ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $filters['statut']);
        }

        // Filtre par offre spécifique
        if (!empty($filters['offreId'])) {
            $qb->andWhere('o.id = :offreId')
                ->setParameter('offreId', $filters['offreId']);
        }

        // Filtre par type de contrat
        if (!empty($filters['typeContrat'])) {
            $qb->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $filters['typeContrat']);
        }

        // Filtre par date de début
        if (!empty($filters['dateDebut'])) {
            $qb->andWhere('p.datePostulation >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($filters['dateDebut'] . ' 00:00:00'));
        }

        // Filtre par date de fin
        if (!empty($filters['dateFin'])) {
            $qb->andWhere('p.datePostulation <= :dateFin')
                ->setParameter('dateFin', new \DateTime($filters['dateFin'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search & Filter for Candidate "Mes Postulations" page
     * Simple and optimized for the candidate side
     * @param array<string, mixed> $filters
     * @return Postulation[]
     */
    public function searchPostulationsForCandidate(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.datePostulation', 'DESC');

        // Filtre par mot-clé (titre de l'offre, entreprise, motivation)
        if (!empty($filters['keyword'])) {
            $qb->andWhere('
                o.titre LIKE :keyword 
                OR o.entreprise LIKE :keyword 
                OR p.motivationCandidature LIKE :keyword
            ')
                ->setParameter('keyword', '%' . trim($filters['keyword']) . '%');
        }

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $filters['statut']);
        }

        // Filtre par nom de l'offre (if you want a separate "offre" field)
        if (!empty($filters['offre'])) {
            $qb->andWhere('o.titre LIKE :offre')
                ->setParameter('offre', '%' . trim($filters['offre']) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques détaillées pour le dashboard recruteur
     * @return array{
     *     total:int,
     *     accepted:int,
     *     refused:int,
     *     pending:int,
     *     by_offre:list<array<string, mixed>>
     * }
     */
    public function getDetailedStatsForRecruiter(User $recruiter): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $recruiter->getRoles());

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->select('COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statut = :accepted THEN 1 ELSE 0 END) as accepted')
            ->addSelect('SUM(CASE WHEN p.statut = :refused THEN 1 ELSE 0 END) as refused')
            ->addSelect('SUM(CASE WHEN p.statut = :pending THEN 1 ELSE 0 END) as pending')
            ->setParameter('accepted', 'Acceptée')
            ->setParameter('refused', 'Refusée')
            ->setParameter('pending', 'En attente');

        if (!$isAdmin) {
            $qb->andWhere('o.user = :recruiter')
                ->setParameter('recruiter', $recruiter);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        // Statistiques par offre
        $offreStats = $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->select('o.id as offreId, o.titre as offreTitre, COUNT(p.id) as total')
            ->addSelect('SUM(CASE WHEN p.statut = :accepted THEN 1 ELSE 0 END) as accepted')
            ->addSelect('SUM(CASE WHEN p.statut = :pending THEN 1 ELSE 0 END) as pending')
            ->setParameter('accepted', 'Acceptée')
            ->setParameter('pending', 'En attente');

        if (!$isAdmin) {
            $offreStats->andWhere('o.user = :recruiter')
                ->setParameter('recruiter', $recruiter);
        }

        $offreStats->groupBy('o.id')
            ->orderBy('total', 'DESC');

        return [
            'total' => (int) ($result['total'] ?? 0),
            'accepted' => (int) ($result['accepted'] ?? 0),
            'refused' => (int) ($result['refused'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'by_offre' => $offreStats->getQuery()->getResult(),
        ];
    }

    /**
     * Évolution des candidatures par mois
     * @return array<string, int>
     */
    public function getPostulationsEvolution(User $recruiter, int $months = 6): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $recruiter->getRoles());

        $evolution = [];
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-$months months");

        for ($i = $months; $i >= 0; $i--) {
            $month = (clone $endDate)->modify("-$i months");
            $monthKey = $month->format('Y-m');
            $evolution[$monthKey] = 0;
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->select('SUBSTRING(p.datePostulation, 1, 7) as month, COUNT(p.id) as count')
            ->where('p.datePostulation BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if (!$isAdmin) {
            $qb->andWhere('o.user = :recruiter')
                ->setParameter('recruiter', $recruiter);
        }

        $results = $qb->getQuery()->getResult();

        foreach ($results as $result) {
            $evolution[$result['month']] = (int) $result['count'];
        }

        return $evolution;
    }
}
