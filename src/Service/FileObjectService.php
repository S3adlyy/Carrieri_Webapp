<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Artifact;
use App\Entity\FileObject;
use App\Repository\FileObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileObjectService
{
    public function __construct(
        private readonly FileObjectRepository $fileObjectRepository,
        private readonly EntityManagerInterface $em,
        private readonly S3Service $s3Service
    ) {
    }

    public function uploadNewVersion(
        Artifact $artifact,
        UploadedFile $file,
        int $candidateId
    ): FileObject {
        $ext = S3KeyUtil::extNoDotOrDefault($file->getClientOriginalName());
        $contentType = S3KeyUtil::contentTypeFromExt($ext);

        $track = $artifact->getTrack();
        $trackId = $track?->getId();
        $artifactId = $artifact->getId();

        if ($trackId === null || $artifactId === null) {
            throw new \RuntimeException('Track or artifact ID missing');
        }

        $key = S3KeyUtil::artifactFileKey(
            $candidateId,
            $trackId,
            $artifactId,
            $ext
        );

        $this->s3Service->uploadFile($file, $key, $contentType);

        $fileObject = new FileObject();
        $fileObject->setArtifact($artifact);
        $fileObject->setArtifactId($artifactId);
        $fileObject->setStorageKey($key);
        $fileObject->setPublicUrl('s3://carrieri-storage-dev-islem/' . $key);
        $fileObject->setMimeType($contentType);
        $fileObject->setFileSize((int) $file->getSize());
        $fileObject->setUploadedAt(new \DateTimeImmutable());

        $this->em->persist($fileObject);
        $this->em->flush();

        return $fileObject;
    }

    /**
     * @return FileObject[]
     */
    public function listHistoryByArtifact(Artifact $artifact): array
    {
        return $this->fileObjectRepository->findBy(
            ['artifact' => $artifact],
            ['uploadedAt' => 'DESC']
        );
    }

    public function findLatestByArtifact(Artifact $artifact): ?FileObject
    {
        return $this->fileObjectRepository->findOneBy(
            ['artifact' => $artifact],
            ['uploadedAt' => 'DESC']
        );
    }

    public function findById(int $id): ?FileObject
    {
        return $this->fileObjectRepository->find($id);
    }

    public function presignedDownloadUrl(string $storageKey, int $expirySeconds = 600): string
    {
        return $this->s3Service->presignedDownloadUrl($storageKey, $expirySeconds);
    }
}