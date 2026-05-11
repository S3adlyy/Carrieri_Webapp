<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Snapshot;
use App\Entity\SnapshotItem;
use App\Entity\Track;
use App\Repository\SnapshotRepository;
use App\Repository\SnapshotItemRepository;
use App\Service\ArtifactService;
use App\Service\FileObjectService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class SnapshotService
{
    public function __construct(
        private readonly SnapshotRepository     $snapshotRepo,
        private readonly SnapshotItemRepository $snapshotItemRepo,
        private readonly ArtifactService        $artifactService,
        private readonly FileObjectService      $fileObjectService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Creates a snapshot of all current artifacts in the track.
     * Each artifact's latest FileObject is captured in a SnapshotItem.
     * @throws \Exception
     */
    public function create(Track $track, User $user, string $title, string $message): Snapshot
    {
        $snapshot = new Snapshot();
        $snapshot->setTrack($track);
        $snapshot->setUser($user);
        $snapshot->setTitle($title);
        $snapshot->setMessage($message);
        $snapshot->setCreatedAt(new \DateTime());  // Changed from DateTimeImmutable
        $snapshot->setIsFinal(false);

        // Debug: Check values before persist
        $this->em->persist($snapshot);

        $artifacts = $this->artifactService->listActiveByTrack($track);
        foreach ($artifacts as $artifact) {
            $latestFo = $this->fileObjectService->findLatestByArtifact($artifact);
            $item = new SnapshotItem();
            $item->setSnapshot($snapshot);
            $item->setArtifact($artifact);
            $item->setFileObject($latestFo);
            $this->em->persist($item);

        }


        try {
            $this->em->flush();
        } catch (\Exception $e) {
            dump($e->getMessage());
            throw $e;
        }

        return $snapshot;
    }

    public function delete(Snapshot $snapshot): void
    {
        $items = $this->snapshotItemRepo->findBy(['snapshot' => $snapshot]);

        foreach ($items as $item) {
            $this->em->remove($item);
        }

        $this->em->remove($snapshot);
        $this->em->flush();
    }

    /** @return Snapshot[] oldest first */
    public function listByTrack(Track $track): array
    {
        return $this->snapshotRepo->findBy(
            ['track' => $track],
            ['createdAt' => 'ASC']
        );
    }

    public function findById(int $id): ?Snapshot
    {
        return $this->snapshotRepo->find($id);
    }

    /** @return SnapshotItem[] */
    public function getItems(Snapshot $snapshot): array
    {
        return $this->snapshotItemRepo->findBy(['snapshot' => $snapshot]);
    }
}