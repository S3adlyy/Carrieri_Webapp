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
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(id) FROM favorites_offres WHERE candidat_id = ? AND offre_id = ?',
            [$candidatId, $offreId]
        );

        return (int) $count > 0;
    }

    public function addFavorite(int $candidatId, int $offreId): bool
    {
        if ($this->isFavorite($candidatId, $offreId)) {
            return false;
        }

        // Use a direct DBAL insert to avoid PDO trying to cast DateTime objects to string.
        $this->getEntityManager()->getConnection()->insert('favorites_offres', [
            'candidat_id' => $candidatId,
            'offre_id' => $offreId,
            'date_ajout' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function removeFavorite(int $candidatId, int $offreId): bool
    {
        $affected = $this->getEntityManager()->getConnection()->delete('favorites_offres', [
            'candidat_id' => $candidatId,
            'offre_id' => $offreId,
        ]);

        return $affected > 0;
    }

    public function getFavoritesByCandidat(int $candidatId): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, candidat_id, offre_id, date_ajout FROM favorites_offres WHERE candidat_id = ? ORDER BY date_ajout DESC',
            [$candidatId]
        );
    }

    public function getFavoriteOfferIdsByCandidat(int $candidatId): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT offre_id FROM favorites_offres WHERE candidat_id = ?',
            [$candidatId]
        );

        return array_map(static fn($value) => (int) $value, $rows);
    }
}