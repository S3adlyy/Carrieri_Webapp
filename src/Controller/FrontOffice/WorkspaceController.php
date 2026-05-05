<?php


declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Entity\Track;
use App\Entity\User;
use App\Service\TrackService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/workspace', name: 'workspace_')]
class WorkspaceController extends AbstractController
{
    use UserTypeCasterTrait;
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly TrackService $trackService,
    ) {
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        $ws = $this->workspaceService->getOrCreateByUser($user);
        $tracks = $this->trackService->listByWorkspace($ws);

        return $this->json([
            'workspace' => [
                'id' => $ws->getId(),
                'description' => $ws->getDescription(),
                'createdAt' => $ws->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
            'tracks' => array_map(fn (Track $t) => $this->serializeTrack($t), $tracks),
        ]);
    }

    #[Route('/track/create', name: 'track_create', methods: ['POST'])]
    public function createTrack(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $title = trim((string) ($data['title'] ?? ''));
        $category = strtoupper((string) ($data['category'] ?? 'PROJECT'));
        $visibility = strtoupper((string) ($data['visibility'] ?? 'PRIVATE'));
        $description = isset($data['description']) ? trim((string) $data['description']) : null;

        $startDate = !empty($data['startDate']) ? new \DateTimeImmutable($data['startDate']) : null;
        $endDate = !empty($data['endDate']) ? new \DateTimeImmutable($data['endDate']) : null;

        if ($title === '') {
            return $this->json(['error' => 'Title is required.'], 422);
        }

        $ws = $this->workspaceService->getOrCreateByUser($user);
        $track = $this->trackService->create(
            $ws,
            $title,
            $category,
            $visibility,
            $startDate,
            $endDate,
            $description
        );

        dump($track);

        return $this->json([
            'track' => $this->serializeTrack($track),
        ], 201);
    }

    #[Route('/track/{id}/update', name: 'track_update', methods: ['POST'])]
    public function updateTrack(int $id, Request $request): JsonResponse
    {
        $track = $this->trackService->findById($id);
        if (!$track) {
            return $this->json(['error' => 'Track not found.'], 404);
        }
        if (!$this->isOwner($track)) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            return $this->json(['error' => 'Title is required.'], 422);
        }

        $startDate = !empty($data['startDate']) ? new \DateTimeImmutable($data['startDate']) : null;
        $endDate = !empty($data['endDate']) ? new \DateTimeImmutable($data['endDate']) : null;

        $track = $this->trackService->update(
            $track,
            $title,
            (string) ($data['category'] ?? 'PROJECT'),
            (string) ($data['visibility'] ?? 'PRIVATE'),
            $startDate,
            $endDate,
            $data['description'] ?? null
        );

        return $this->json([
            'track' => $this->serializeTrack($track),
        ]);
    }

    #[Route('/track/{id}/delete', name: 'track_delete', methods: ['POST'])]
    public function deleteTrack(int $id): JsonResponse
    {
        $track = $this->trackService->findById($id);
        if (!$track) {
            return $this->json(['error' => 'Track not found.'], 404);
        }
        if (!$this->isOwner($track)) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $this->trackService->softDelete($track);

        return $this->json(['ok' => true]);
    }

    private function isOwner(Track $track): bool
    {
        $user = $this->getAuthenticatedUser();

        $workspace = $track->getWorkspace();
        $owner = $workspace?->getUser();

        return $user instanceof User
            && $owner instanceof User
            && $owner->getId() === $user->getId();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function serializeTrack(Track $t): array
    {
        return [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'category' => $t->getCategory(),
            'visibility' => $t->getVisibility(),
            'description' => $t->getDescription(),
            'startDate' => $t->getStartDate()?->format('Y-m-d'),
            'endDate' => $t->getEndDate()?->format('Y-m-d'),
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
