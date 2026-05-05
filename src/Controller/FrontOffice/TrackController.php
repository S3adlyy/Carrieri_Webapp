<?php
declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Entity\Track;
use App\Service\ArtifactService;
use App\Service\FileObjectService;
use App\Service\TrackService;
use http\Client\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\UserTypeCasterTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/track')]
final class TrackController extends AbstractController
{
    use UserTypeCasterTrait;
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
        try {
            $track = $this->trackService->findById($id);
            if (!$track) {
                return $this->json(['error' => 'Track not found.'], 404);
            }

            if (!$this->isTrackOwner($track)) {
                return $this->json(['error' => 'Forbidden.'], 403);
            }

            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = $request->request->all();
            }

            $name = trim((string) ($data['name'] ?? ''));
            $type = strtoupper(trim((string) ($data['type'] ?? 'OTHER')));
            $language = isset($data['language']) && trim((string) $data['language']) !== ''
                ? trim((string) $data['language'])
                : null;
            $textContent = isset($data['textContent']) && trim((string) $data['textContent']) !== ''
                ? trim((string) $data['textContent'])
                : null;
            $description = isset($data['description'])
                ? trim((string) $data['description'])
                : '';

            if ($name === '') {
                return $this->json(['error' => 'Name is required.'], 422);
            }

            if (!in_array($type, ['CODE', 'DOCUMENT', 'IMAGE', 'VIDEO', 'AUDIO', 'LINK', 'TEXT', 'OTHER'], true)) {
                $type = 'OTHER';
            }

            if (in_array($type, ['TEXT', 'LINK'], true) && $textContent === null) {
                return $this->json(['error' => 'Text content is required for this artifact type.'], 422);
            }

            $artifact = $this->artifactService->create(
                $track,
                $name,
                $description,
                $type,
                $language,
                $textContent
            );

            return $this->json([
                'artifact' => $this->serializeArtifact($artifact),
            ], 201);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Create artifact failed: ' . $e->getMessage(),
            ], 500);
        }
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
        $originalName = (string) $file->getClientOriginalName();
        $artifactType = strtoupper((string) $artifact->getArtifactType());

        if (!$this->isAllowedUploadForArtifactType($artifactType, $originalName)) {
            return $this->json(['error' => 'File type does not match artifact type.'], 422);
        }

        $track = $artifact->getTrack();
        $workspace = $track?->getWorkspace();
        if (!$track || !$workspace) {
            return $this->json(['error' => 'Workspace not found.'], 500);
        }
        $workspaceUser = $workspace->getUser();
        if (!$workspaceUser) {
            return $this->json(['error' => 'User not found for workspace.'], 500);
        }
        $candidateId = $workspaceUser->getId();
        if ($candidateId === null) {
            return $this->json(['error' => 'User ID not found.'], 500);
        }
        $fo = $this->fileObjectService->uploadNewVersion($artifact, $file, $candidateId);

        return $this->json([
            'ok'           => true,
            'fileObjectId' => $fo->getId(),
            'originalName' => basename((string) $fo->getStorageKey()),
            'fileSize'     => $fo->getFileSize(),
        ], 201);
    }

    /** Presigned download URL for a FileObject */
    #[Route('/fileobject/{id}/download-url', name: 'track_fileobject_url', methods: ['GET'])]
    public function downloadUrl(int $id): JsonResponse
    {
        $fo = $this->fileObjectService->findById($id);
        if (!$fo) return $this->json(['error' => 'Not found.'], 404);

        $url = $this->fileObjectService->presignedDownloadUrl((string) $fo->getStorageKey(), 600);
        return $this->json(['url' => $url]);
    }
    #[Route('/fileobject/{id}/download', name: 'track_fileobject_download', methods: ['GET'])]
    public function download(int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $fo = $this->fileObjectService->findById($id);
        if (!$fo) {
            throw $this->createNotFoundException('File not found.');
        }

        $url = $this->fileObjectService->presignedDownloadUrl((string) $fo->getStorageKey(), 600);

        return $this->redirect($url, 302);
    }

    private function isAllowedUploadForArtifactType(string $artifactType, string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $map = [
            'IMAGE' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
            'VIDEO' => ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v'],
            'AUDIO' => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
            'DOCUMENT' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'txt', 'rtf', 'odt'],
            'CODE' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'c', 'cpp', 'cc', 'h', 'hpp', 'java', 'py', 'js', 'ts', 'jsx', 'tsx', 'php', 'html', 'css', 'scss', 'sass', 'json', 'xml', 'yml', 'yaml', 'sql', 'sh', 'bat', 'md', 'txt'],
        ];

        if (!isset($map[$artifactType])) {
            return true;
        }

        return in_array($ext, $map[$artifactType], true);
    }

    // ─── Private ─────────────────────────────────────────────────────────

    private function isTrackOwner(\App\Entity\Track $t): bool
    {
        $trackWorkspace = $t->getWorkspace();
        if (!$trackWorkspace) {
            return false;
        }
        $trackUser = $trackWorkspace->getUser();
        if (!$trackUser) {
            return false;
        }
        $trackUserId = $trackUser->getId();
        $currentUser = $this->requireUser();
        $currentUserId = $currentUser->getId();
        return $trackUserId === $currentUserId;
    }

    private function isArtifactOwner(\App\Entity\Artifact $a): bool
    {
        $track = $a->getTrack();

        return $track instanceof Track && $this->isTrackOwner($track);
    }

    /**
     * @return array{id:int|null,name:string|null,type:string|null,language:string|null,textContent:string|null,description:string|null,createdAt:string|null,hasFile:bool,fileObjectId:int|null}
     */
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

    private function requireUser(): User
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
