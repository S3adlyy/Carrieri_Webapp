<?php
declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Service\SnapshotService;
use App\Service\TrackService;
use App\Service\FileObjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/snapshot')]
final class SnapshotController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private readonly SnapshotService   $snapshotService,
        private readonly TrackService      $trackService,
        private readonly FileObjectService $fileObjectService,
    ) {}

    /** Create a snapshot for a track
     * @throws \Exception
     */
    #[Route('/create/{trackId}', name: 'snapshot_create', methods: ['POST'])]
    public function create(int $trackId, Request $request): JsonResponse
    {
        $track = $this->trackService->findById($trackId);
        if (!$track) return $this->json(['error' => 'Track not found.'], 404);

        $workspace = $track->getWorkspace();
        $workspaceUser = $workspace?->getUser();
        $currentUser = $this->requireUser();
        if (!$workspace || !$workspaceUser || $workspaceUser->getId() !== $currentUser->getId()) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $data    = json_decode($request->getContent(), true) ?? [];
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($title === '') {
            return $this->json(['error' => 'Title is required.'], 422);
        }
        if ($message === '') return $this->json(['error' => 'Message is required.'], 422);
        if (strlen($message) > 280) return $this->json(['error' => 'Message too long (max 280 chars).'], 422);

        $currentUserId = $currentUser->getId();
        if ($currentUserId === null) {
            return $this->json(['error' => 'User not found.'], 403);
        }

        $snapshot = $this->snapshotService->create($track, $currentUser,$title, $message);
        dump($snapshot);

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
                $artifact = $item->getArtifact();
                if (!$artifact) {
                    return [
                        'artifactId' => null,
                        'artifactName' => null,
                        'artifactType' => null,
                        'language' => null,
                        'textContent' => null,
                        'description' => null,
                        'fileObjectId' => $fo?->getId(),
                        'hasFile' => $fo !== null,
                        'downloadUrl' => null,
                    ];
                }

                return [
                    'artifactId'   => $artifact->getId(),
                    'artifactName' => $artifact->getArtifactName(),
                    'artifactType' => $artifact->getArtifactType(),
                    'textContent'  => $artifact->getTestContent(),
                    'fileObjectId' => $fo?->getId(),
                    'hasFile'      => $fo !== null,
                    'downloadUrl'  => $fo ? $this->fileObjectService->presignedDownloadUrl((string) $fo->getStorageKey(), 600) : null,
                ];
            }, $items),
        ]);
    }

    #[Route('/{id}/delete', name: 'snapshot_delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        $snapshot = $this->snapshotService->findById($id);
        if (!$snapshot) {
            return $this->json(['error' => 'Snapshot not found.'], 404);
        }

        $track = $snapshot->getTrack();
        if (!$track) {
            return $this->json(['error' => 'Track not found.'], 404);
        }

        $workspace = $track->getWorkspace();
        $workspaceUser = $workspace?->getUser();
        $currentUser = $this->requireUser();

        if (!$workspace || !$workspaceUser || $workspaceUser->getId() !== $currentUser->getId()) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $this->snapshotService->delete($snapshot);

        return $this->json(['ok' => true]);
    }


    /**
     * @return array{id:int,title:string|null,message:string|null,createdAt:string|null,isFinal:bool}
     */
    private function serializeSnapshot(\App\Entity\Snapshot $s): array
    {
        return [
            'id'        => $s->getId(),
            'title'     => $s->getTitle(),
            'message'   => $s->getMessage(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i'),
            'isFinal'   => (bool) $s->getIsFinal(),
        ];
    }

    private function requireUser(): User
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
