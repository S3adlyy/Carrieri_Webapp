<?php

namespace App\Repository;

use App\Entity\FavoritesOffres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoritesOffresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoritesOffres::class);
    }

    public function isFavorite(int $candidatId, int $offreId): bool
    {
        return (bool) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.candidatId = :candidatId')
            ->andWhere('f.offreId = :offreId')
            ->setParameters(['candidatId' => $candidatId, 'offreId' => $offreId])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function addFavorite(int $candidatId, int $offreId): bool
    {
        if ($this->isFavorite($candidatId, $offreId)) {
            return false;
        }

        $favorite = new FavoritesOffres();
        $favorite->setCandidatId($candidatId);
        $favorite->setOffreId($offreId);
        $favorite->setDateAjout(new \DateTime());

        $this->getEntityManager()->persist($favorite);
        $this->getEntityManager()->flush();

        return true;
    }

    public function removeFavorite(int $candidatId, int $offreId): bool
    {
        $favorite = $this->findOneBy([
            'candidatId' => $candidatId,
            'offreId' => $offreId
        ]);

        if (!$favorite) {
            return false;
        }

        $this->getEntityManager()->remove($favorite);
        $this->getEntityManager()->flush();

        return true;
    }

    // Improved method with JOIN to load the offer
    public function getFavoritesByCandidat(int $candidatId): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.offreEmploi', 'o')   // Important: load the offer
            ->addSelect('o')
            ->where('f.candidatId = :candidatId')
            ->orderBy('f.dateAjout', 'DESC')
            ->setParameter('candidatId', $candidatId)
            ->getQuery()
            ->getResult();
    }
}