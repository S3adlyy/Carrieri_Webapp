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
}
