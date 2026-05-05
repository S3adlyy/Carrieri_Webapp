<?php
declare(strict_types=1);
namespace App\Controller\FrontOffice;

use App\Service\ArtifactService;
use App\Service\CodeBrowseService;
use App\Service\FileObjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/workspace/code')]
final class CodeViewerController extends AbstractController
{
    public function __construct(
        private readonly ArtifactService   $artifactService,
        private readonly FileObjectService $fileObjectService,
        private readonly CodeBrowseService $codeBrowseService,
    ) {}

    /** Full-screen code viewer page */
    #[Route('/{artifactId}', name: 'codeviewer', methods: ['GET'])]
    public function viewer(int $artifactId, Request $request): Response
    {
        $artifact = $this->artifactService->findById($artifactId);
        if (!$artifact) {
            throw $this->createNotFoundException();
        }

        $foId = $request->query->getInt('fo', 0);
        $fo = $foId
            ? $this->fileObjectService->findById($foId)
            : $this->fileObjectService->findLatestByArtifact($artifact);

        $storageKey = $fo?->getStorageKey() ?? '';
        $ext = strtolower(pathinfo($storageKey, PATHINFO_EXTENSION));
        $isZip = in_array($ext, ['zip'], true);
        $mode = $request->query->get('mode', $isZip ? 'tree' : 'preview');

        return $this->render('FrontOffice/workspace/code_viewer.html.twig', [
            'artifact' => $artifact,
            'fileObject' => $fo,
            'hasFile' => $fo !== null,
            'downloadUrl' => $fo ? $this->fileObjectService->presignedDownloadUrl($storageKey, 1800) : null,
            'viewerMode' => $mode,
            'fileExt' => $ext,
            'mimeType' => $fo?->getMimeType(),
        ]);
    }

    #[Route('/{artifactId}/raw', name: 'code_viewer_raw', methods: ['GET'])]
    public function raw(int $artifactId, Request $request): JsonResponse
    {
        $artifact = $this->artifactService->findById($artifactId);
        if (!$artifact) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $foId = $request->query->getInt('fo', 0);
        $fo = $foId
            ? $this->fileObjectService->findById($foId)
            : $this->fileObjectService->findLatestByArtifact($artifact);

        if (!$fo) {
            return $this->json(['error' => 'No file.'], 404);
        }

        $content = $this->codeBrowseService->readRawFile($fo);
        $language = CodeBrowseService::languageFromStorageKey((string) $fo->getStorageKey());

        return $this->json([
            'content' => $content,
            'language' => $language,
            'path' => basename((string) $fo->getStorageKey()),
        ]);
    }
    /** Returns the file tree of a ZIP artifact as JSON */
    #[Route('/{artifactId}/tree', name: 'code_viewer_tree', methods: ['GET'])]
    public function tree(int $artifactId, Request $request): JsonResponse
    {
        $artifact = $this->artifactService->findById($artifactId);
        if (!$artifact) return $this->json(['error' => 'Not found.'], 404);

        $foId = $request->query->getInt('fo', 0);
        $fo   = $foId ? $this->fileObjectService->findById($foId)
            : $this->fileObjectService->findLatestByArtifact($artifact);

        if (!$fo) return $this->json(['error' => 'No file uploaded yet.'], 404);

        $tree = $this->codeBrowseService->buildTree($fo);
        return $this->json(['tree' => $tree]);
    }

    /** Returns the content of a single file entry inside the ZIP */
    #[Route('/{artifactId}/file', name: 'code_viewer_file', methods: ['GET'])]
    public function file1(int $artifactId, Request $request): JsonResponse
    {
        $artifact = $this->artifactService->findById($artifactId);
        if (!$artifact) return $this->json(['error' => 'Not found.'], 404);

        $foId      = $request->query->getInt('fo', 0);
        $entryPath = $request->query->getString('path', '');

        $fo = $foId ? $this->fileObjectService->findById($foId)
            : $this->fileObjectService->findLatestByArtifact($artifact);

        if (!$fo)           return $this->json(['error' => 'No file.'], 404);
        if ($entryPath === '') return $this->json(['error' => 'No path specified.'], 422);

        $content  = $this->codeBrowseService->readEntry($fo, $entryPath);
        $language = CodeBrowseService::languageFromPath($entryPath);
        return $this->json([
            'content' => $content,
            'language' => $language,
            'path' => $entryPath,
            'debug_length' => strlen($content),
        ]);

    }
}
