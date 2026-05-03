<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function getOrCreateByUser(User $user): Workspace
    {
        $workspace = $this->workspaceRepository->findOneBy(['user' => $user]);

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $workspace = new Workspace();
        $workspace->setUser($user);
        //$workspace->setCandidatId($user->getId());
        $workspace->setDescription('Personal workspace');
        $workspace->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($workspace);
        $this->em->flush();

        return $workspace;
    }

    public function findByUser(User $user): ?Workspace
    {
        return $this->workspaceRepository->findOneBy(['user' => $user]);
    }
}