<?php

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
            $qb->andWhere('o.titre LIKE :search OR o.entreprise LIKE :search OR o.localisation LIKE :search')
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

}
