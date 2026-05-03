<?php
declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Service\ArtifactService;
use App\Service\FileObjectService;
use App\Service\TrackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/track')]
final class TrackController extends AbstractController
{
    public function __construct(
        private readonly TrackService       $trackService,
        private readonly ArtifactService    $artifactService,
        private readonly FileObjectService  $fileObjectService,
    ) {}

    /** List active artifacts in a track */
    #[Route('/{id}/artifacts', name: 'track_artifacts', methods: ['GET'])]
    public function artifacts(int $id): JsonResponse
    {
        $track = $this->trackService->findById($id);
        if (!$track) return $this->json(['error' => 'Not found.'], 404);

        $artifacts = $this->artifactService->listActiveByTrack($track);

        return $this->json([
            'artifacts' => array_map(fn($a) => $this->serializeArtifact($a), $artifacts),
        ]);
    }

    /** Create an artifact */
    #[Route('/{id}/artifact/create', name: 'track_artifact_create', methods: ['POST'])]
    public function createArtifact(int $id, Request $request): JsonResponse
    {
        $track = $this->trackService->findById($id);
        if (!$track) return $this->json(['error' => 'Not found.'], 404);
        if (!$this->isTrackOwner($track)) return $this->json(['error' => 'Forbidden.'], 403);

        $data        = json_decode($request->getContent(), true) ?? [];
        $name        = trim($data['name'] ?? '');
        $type        = strtoupper($data['type'] ?? '');
        $language    = $data['language'] ?? null;
        $textContent = $data['textContent'] ?? null;
        $description = $data['description'] ?? null;

        if ($name === '') return $this->json(['error' => 'Name is required.'], 422);
        if ($type === '') return $this->json(['error' => 'Type is required.'], 422);

        $artifact = $this->artifactService->create($track, $name, $description, $type, $language, $textContent);
        return $this->json(['artifact' => $this->serializeArtifact($artifact)], 201);
    }

    /** Rename an artifact */
    #[Route('/artifact/{id}/rename', name: 'track_artifact_rename', methods: ['POST'])]
    public function renameArtifact(int $id, Request $request): JsonResponse
    {
        $artifact = $this->artifactService->findById($id);
        if (!$artifact) return $this->json(['error' => 'Not found.'], 404);
        if (!$this->isArtifactOwner($artifact)) return $this->json(['error' => 'Forbidden.'], 403);

        $data    = json_decode($request->getContent(), true) ?? [];
        $newName = trim($data['name'] ?? '');
        if ($newName === '') return $this->json(['error' => 'Name cannot be empty.'], 422);

        $this->artifactService->rename($artifact, $newName);
        return $this->json(['ok' => true, 'name' => $newName]);
    }

    /** Soft-delete an artifact */
    #[Route('/artifact/{id}/delete', name: 'track_artifact_delete', methods: ['POST'])]
    public function deleteArtifact(int $id): JsonResponse
    {
        $artifact = $this->artifactService->findById($id);
        if (!$artifact) return $this->json(['error' => 'Not found.'], 404);
        if (!$this->isArtifactOwner($artifact)) return $this->json(['error' => 'Forbidden.'], 403);

        $this->artifactService->softDelete($artifact);
        return $this->json(['ok' => true]);
    }

    /** Upload a new file version for a FILE/CODE/IMAGE/etc artifact */
    #[Route('/artifact/{id}/upload', name: 'track_artifact_upload', methods: ['POST'])]
    public function uploadVersion(int $id, Request $request): JsonResponse
    {
        $artifact = $this->artifactService->findById($id);
        if (!$artifact) return $this->json(['error' => 'Not found.'], 404);
        if (!$this->isArtifactOwner($artifact)) return $this->json(['error' => 'Forbidden.'], 403);

        $file = $request->files->get('file');
        if (!$file) return $this->json(['error' => 'No file uploaded.'], 422);

        $maxBytes = 50 * 1024 * 1024; // 50 MB
        if ($file->getSize() > $maxBytes) {
            return $this->json(['error' => 'File exceeds 50 MB limit.'], 422);
        }

        $candidateId = $artifact->getTrack()->getWorkspace()->getUser()->getId();
        $fo = $this->fileObjectService->uploadNewVersion($artifact, $file, $candidateId);

        return $this->json([
            'ok'           => true,
            'fileObjectId' => $fo->getId(),
            'originalName' => basename($fo->getStorageKey()),
            'fileSize'     => $fo->getFileSize(),
        ], 201);
    }

    /** Presigned download URL for a FileObject */
    #[Route('/fileobject/{id}/download-url', name: 'track_fileobject_url', methods: ['GET'])]
    public function downloadUrl(int $id): JsonResponse
    {
        $fo = $this->fileObjectService->findById($id);
        if (!$fo) return $this->json(['error' => 'Not found.'], 404);

        $url = $this->fileObjectService->presignedDownloadUrl($fo->getStorageKey(), 600);
        return $this->json(['url' => $url]);
    }

    // ─── Private ─────────────────────────────────────────────────────────

    private function isTrackOwner(\App\Entity\Track $t): bool
    {
        return $t->getWorkspace()->getUser()->getId() === $this->getUser()->getId();
    }

    private function isArtifactOwner(\App\Entity\Artifact $a): bool
    {
        return $this->isTrackOwner($a->getTrack());
    }

    private function serializeArtifact(\App\Entity\Artifact $a): array
    {
        $latestFo = $this->fileObjectService->findLatestByArtifact($a);
        return [
            'id'          => $a->getId(),
            'name'        => $a->getArtifactName(),
            'type'        => $a->getArtifactType(),
            'language'    => $a->getLanguage(),
            'textContent' => $a->getTestContent(),
            'description' => $a->getArtifactDescription(),
            'createdAt'   => $a->getCreatedAt()?->format('Y-m-d'),
            'hasFile'     => $latestFo !== null,
            'fileObjectId'=> $latestFo?->getId(),
        ];
    }
}