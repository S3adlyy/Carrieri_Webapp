<?php
// src/Repository/OffreEmploiRepository.php

namespace App\Repository;

use App\Entity\OffreEmploi;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OffreEmploi>
 */
class OffreEmploiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OffreEmploi::class);
    }

    public function findByUserWithSearch(User $user, string $search = ''): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());

        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.id', 'DESC');

        if (!$isAdmin) {
            $qb->andWhere('o.user = :user')
                ->setParameter('user', $user);
        }

        if ($search !== '') {
            $qb->andWhere('o.titre LIKE :search OR o.entreprise LIKE :search OR o.localisation LIKE :search OR o.typeContrat LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findActiveOffers(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.dateExpiration > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('o.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchAndFilter(?string $keyword, ?string $type, ?string $localisation, ?float $salaireMin): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.dateExpiration IS NULL OR o.dateExpiration > :now')
            ->setParameter('now', new \DateTime());

        if ($keyword && trim($keyword) !== '') {
            $qb->andWhere('
                o.titre LIKE :kw
                OR o.entreprise LIKE :kw
                OR o.localisation LIKE :kw
                OR o.description LIKE :kw
                OR o.typeContrat LIKE :kw
                OR o.niveauQualification LIKE :kw
                OR o.experienceRequise LIKE :kw
                OR o.competencesRequises LIKE :kw
                OR o.secteurActivite LIKE :kw
                OR o.contactRecruteur LIKE :kw
            ')
                ->setParameter('kw', '%' . trim($keyword) . '%');
        }

        if ($type && trim($type) !== '') {
            $qb->andWhere('o.typeContrat = :type')
                ->setParameter('type', trim($type));
        }

        if ($localisation && trim($localisation) !== '') {
            $qb->andWhere('o.localisation LIKE :loc')
                ->setParameter('loc', '%' . trim($localisation) . '%');
        }

        if ($salaireMin !== null && $salaireMin !== 0.0) {
            $qb->andWhere('o.salaire >= :sal')
                ->setParameter('sal', $salaireMin);
        }

        return $qb->orderBy('o.datePublication', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Nouvelle méthode pour la recherche avancée dans le back office
    public function searchOffersWithFilters(User $user, array $filters = []): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());

        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->orderBy('o.datePublication', 'DESC');

        if (!$isAdmin) {
            $qb->andWhere('o.user = :user')
                ->setParameter('user', $user);
        }

        // Filtre par mot-clé
        if (!empty($filters['keyword'])) {
            $qb->andWhere('
                o.titre LIKE :keyword 
                OR o.entreprise LIKE :keyword 
                OR o.localisation LIKE :keyword 
                OR o.typeContrat LIKE :keyword
                OR u.email LIKE :keyword
                OR u.firstName LIKE :keyword
                OR u.lastName LIKE :keyword
            ')
                ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        // Filtre par type de contrat
        if (!empty($filters['typeContrat'])) {
            $qb->andWhere('o.typeContrat = :type')
                ->setParameter('type', $filters['typeContrat']);
        }

        // Filtre par statut (active/expirée)
        if (!empty($filters['statut'])) {
            $now = new \DateTime();
            if ($filters['statut'] === 'active') {
                $qb->andWhere('o.dateExpiration > :now')
                    ->setParameter('now', $now);
            } elseif ($filters['statut'] === 'expiree') {
                $qb->andWhere('o.dateExpiration <= :now')
                    ->setParameter('now', $now);
            }
        }

        // Filtre par salaire min
        if (!empty($filters['salaireMin'])) {
            $qb->andWhere('o.salaire >= :salaireMin')
                ->setParameter('salaireMin', (float) $filters['salaireMin']);
        }

        // Filtre par date de publication
        if (!empty($filters['dateDebut'])) {
            $qb->andWhere('o.datePublication >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }

        if (!empty($filters['dateFin'])) {
            $qb->andWhere('o.datePublication <= :dateFin')
                ->setParameter('dateFin', new \DateTime($filters['dateFin'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    // Statistiques pour le dashboard recruteur
    public function getStatsForUser(User $user): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());

        $qb = $this->createQueryBuilder('o');

        if (!$isAdmin) {
            $qb->where('o.user = :user')
                ->setParameter('user', $user);
        }

        $offres = $qb->getQuery()->getResult();

        $total = count($offres);
        $actives = 0;
        $expirees = 0;
        $today = new \DateTime();

        $parContrat = ['CDI' => 0, 'CDD' => 0, 'Stage' => 0, 'Freelance' => 0];

        foreach ($offres as $offre) {
            if ($offre->getDateExpiration() && $offre->getDateExpiration() > $today) {
                $actives++;
            } else {
                $expirees++;
            }

            $type = $offre->getTypeContrat();
            if ($type && isset($parContrat[$type])) {
                $parContrat[$type]++;
            }
        }

        return [
            'total' => $total,
            'actives' => $actives,
            'expirees' => $expirees,
            'par_contrat' => $parContrat,
        ];
    }
}