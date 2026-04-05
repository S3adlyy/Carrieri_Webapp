<?php

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

    public function getStatsByUser(User $user): array
    {
        return [
            'total' => $this->count(['user' => $user]),
            'accepted' => $this->count(['user' => $user, 'statut' => 'Acceptée']),
            'refused' => $this->count(['user' => $user, 'statut' => 'Refusée']),
            'pending' => $this->count(['user' => $user, 'statut' => 'En attente']),
        ];
    }

    public function findByRecruiter(User $recruiter): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->andWhere('o.user = :recruiter')
            ->setParameter('recruiter', $recruiter)
            ->orderBy('p.datePostulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByOffreAndRecruiter(int $offreId, User $recruiter): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.offreEmploi', 'o')
            ->addSelect('o')
            ->andWhere('o.user = :recruiter')
            ->andWhere('p.offreEmploi = :offreId')
            ->setParameter('recruiter', $recruiter)
            ->setParameter('offreId', $offreId)
            ->orderBy('p.datePostulation', 'DESC')
            ->getQuery()
            ->getResult();
    }

}
