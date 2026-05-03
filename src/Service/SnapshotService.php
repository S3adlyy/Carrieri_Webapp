<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Snapshot;
use App\Entity\SnapshotItem;
use App\Entity\Track;
use App\Repository\SnapshotRepository;
use App\Service\ArtifactService;
use App\Service\FileObjectService;
use Doctrine\ORM\EntityManagerInterface;

final class SnapshotService
{
    public function __construct(
        private readonly SnapshotRepository     $snapshotRepo,
        private readonly ArtifactService        $artifactService,
        private readonly FileObjectService      $fileObjectService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Creates a snapshot of all current artifacts in the track.
     * Each artifact's latest FileObject is captured in a SnapshotItem.
     */
    public function create(Track $track, int $authorId, string $message): Snapshot
    {
        $snapshot = new Snapshot();
        $snapshot->setTrack($track);
        $snapshot->setAuthorId($authorId);
        $snapshot->setMessage($message);
        $snapshot->setCreatedAt(new \DateTimeImmutable());
        $snapshot->setIsFinal(0);
        $this->em->persist($snapshot);

        $artifacts = $this->artifactService->listActive($track);
        foreach ($artifacts as $artifact) {
            $latestFo = $this->fileObjectService->findLatestByArtifact($artifact);
            $item = new SnapshotItem();
            $item->setSnapshot($snapshot);
            $item->setArtifact($artifact);
            $item->setFileObject($latestFo); // null if TEXT/LINK with no upload
            $this->em->persist($item);
        }

        $this->em->flush();
        return $snapshot;
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
        return $snapshot->getItems()->toArray();
    }
}