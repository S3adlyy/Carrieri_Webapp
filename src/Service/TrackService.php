<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Track;
use App\Entity\Workspace;
use App\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TrackService
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * @return Track[]
     */
    public function listByWorkspace(Workspace $workspace): array
    {
        return $this->trackRepository->findBy(
            ['workspace' => $workspace, 'status' => 'ACTIVE'],
            ['createdAt' => 'DESC']
        );
    }

    public function create(
        Workspace $workspace,
        string $title,
        string $category,
        string $visibility,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $description
    ): Track {
        $track = new Track();
        $track->setWorkspace($workspace);
        $track->setWorkspaceId($workspace->getId());
        $track->setTitle($title);
        $track->setCategory(strtoupper($category));
        $track->setVisibility(strtoupper($visibility));
        $track->setDescription($description);
        $track->setStatus('ACTIVE');
        $track->setStartDate($startDate);
        $track->setEndDate($endDate);
        $track->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($track);
        $this->em->flush();

        return $track;
    }

    public function update(
        Track $track,
        string $title,
        string $category,
        string $visibility,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $description
    ): Track {
        $track->setTitle($title);
        $track->setCategory(strtoupper($category));
        $track->setVisibility(strtoupper($visibility));
        $track->setDescription($description);
        $track->setStartDate($startDate);
        $track->setEndDate($endDate);

        $this->em->flush();

        return $track;
    }

    public function updateVisibility(Track $track, string $visibility): void
    {
        $track->setVisibility(strtoupper($visibility));
        $this->em->flush();
    }

    public function softDelete(Track $track): void
    {
        $track->setStatus('DELETED');
        $this->em->flush();
    }

    public function findById(int $id): ?Track
    {
        return $this->trackRepository->find($id);
    }
}