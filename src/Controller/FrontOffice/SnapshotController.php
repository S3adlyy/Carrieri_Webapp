<?php
declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Service\SnapshotService;
use App\Service\TrackService;
use App\Service\FileObjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/snapshot')]
final class SnapshotController extends AbstractController
{
    public function __construct(
        private readonly SnapshotService   $snapshotService,
        private readonly TrackService      $trackService,
        private readonly FileObjectService $fileObjectService,
    ) {}

    /** Create a snapshot for a track */
    #[Route('/create/{trackId}', name: 'snapshot_create', methods: ['POST'])]
    public function create(int $trackId, Request $request): JsonResponse
    {
        $track = $this->trackService->findById($trackId);
        if (!$track) return $this->json(['error' => 'Track not found.'], 404);
        if ($track->getWorkspace()->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $data    = json_decode($request->getContent(), true) ?? [];
        $message = trim($data['message'] ?? '');
        if ($message === '') return $this->json(['error' => 'Message is required.'], 422);
        if (strlen($message) > 280) return $this->json(['error' => 'Message too long (max 280 chars).'], 422);

        $snapshot = $this->snapshotService->create($track, $this->getUser()->getId(), $message);

        return $this->json(['snapshot' => $this->serializeSnapshot($snapshot)], 201);
    }

    /** List snapshots for a track (oldest first) */
    #[Route('/list/{trackId}', name: 'snapshot_list', methods: ['GET'])]
    public function list(int $trackId): JsonResponse
    {
        $track = $this->trackService->findById($trackId);
        if (!$track) return $this->json(['error' => 'Track not found.'], 404);

        $snapshots = $this->snapshotService->listByTrack($track);

        return $this->json([
            'snapshots' => array_map(fn($s) => $this->serializeSnapshot($s), $snapshots),
        ]);
    }

    /** Get snapshot detail with all SnapshotItems */
    #[Route('/{id}/detail', name: 'snapshot_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $snapshot = $this->snapshotService->findById($id);
        if (!$snapshot) return $this->json(['error' => 'Not found.'], 404);

        $items = $this->snapshotService->getItems($snapshot);

        return $this->json([
            'snapshot' => $this->serializeSnapshot($snapshot),
            'items'    => array_map(function ($item) {
                $fo = $item->getFileObject();
                return [
                    'artifactId'   => $item->getArtifact()->getId(),
                    'artifactName' => $item->getArtifact()->getArtifactName(),
                    'artifactType' => $item->getArtifact()->getArtifactType(),
                    'fileObjectId' => $fo?->getId(),
                    'hasFile'      => $fo !== null,
                    'downloadUrl'  => $fo ? $this->fileObjectService->presignedDownloadUrl($fo->getStorageKey(), 600) : null,];
            }, $items),
        ]);
    }

    private function serializeSnapshot(\App\Entity\Snapshot $s): array
    {
        return [
            'id'        => $s->getId(),
            'message'   => $s->getMessage(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i'),
            'isFinal'   => $s->getIsFinal(),
        ];
    }
}