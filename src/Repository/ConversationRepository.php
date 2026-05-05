<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    // src/Repository/ConversationRepository.php

    /**
     * @return Conversation[]
     */
    public function findByRecruteur(User $recruteur): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.recruteur = :recruteur')
            ->setParameter('recruteur', $recruteur)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Ajoutez vos méthodes personnalisées ici
    // public function findBySomething($value): array
    // {
    //     return $this->createQueryBuilder('e')
    //         ->andWhere('e.exampleField = :val')
    //         ->setParameter('val', $value)
    //         ->orderBy('e.id', 'ASC')
    //         ->setMaxResults(10)
    //         ->getQuery()
    //         ->getResult()
    //     ;
    // }
}
