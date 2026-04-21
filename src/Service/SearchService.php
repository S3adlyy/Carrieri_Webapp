<?php

namespace App\Service;

use App\Repository\CoursRepository;
use App\Repository\MissionRepository;
use App\Repository\OffreEmploiRepository;

class SearchService
{
    public function __construct(
        private CoursRepository $coursRepository,
        private MissionRepository $missionRepository,
        private OffreEmploiRepository $offreEmploiRepository
    ) {}

    /**
     * Recherche globale dans tous les modules
     */
    public function globalSearch(string $query): array
    {
        $results = [
            'cours' => $this->searchCours($query),
            'missions' => $this->searchMissions($query),
            'offres' => $this->searchOffres($query),
            'total' => 0
        ];
        
        $results['total'] = count($results['cours']) + count($results['missions']) + count($results['offres']);
        
        return $results;
    }

    /**
     * Recherche avancée avec filtres
     */
    public function advancedSearch(array $filters): array
    {
        $qb = $this->coursRepository->createQueryBuilder('c');
        
        if (!empty($filters['titre'])) {
            $qb->andWhere('c.titre LIKE :titre')
               ->setParameter('titre', '%' . $filters['titre'] . '%');
        }
        
        if (!empty($filters['niveau'])) {
            $qb->andWhere('c.niveau = :niveau')
               ->setParameter('niveau', $filters['niveau']);
        }
        
        if (!empty($filters['prix_min'])) {
            $qb->andWhere('c.prix >= :prix_min')
               ->setParameter('prix_min', $filters['prix_min']);
        }
        
        if (!empty($filters['prix_max'])) {
            $qb->andWhere('c.prix <= :prix_max')
               ->setParameter('prix_max', $filters['prix_max']);
        }
        
        return $qb->getQuery()->getResult();
    }

    private function searchCours(string $query): array
    {
        return $this->coursRepository->createQueryBuilder('c')
            ->where('c.titre LIKE :query')
            ->orWhere('c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function searchMissions(string $query): array
    {
        return $this->missionRepository->createQueryBuilder('m')
            ->where('m.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function searchOffres(string $query): array
    {
        return $this->offreEmploiRepository->createQueryBuilder('o')
            ->where('o.titre LIKE :query')
            ->orWhere('o.entreprise LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}