<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FileObjectRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileObjectRepository::class)]
#[ORM\Table(name: 'file_object')]
class FileObject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'storage_key', type: 'string', length: 255)]
    private string $storageKey;

    #[ORM\Column(name: 'public_url', type: 'string', length: 255)]
    private string $publicUrl;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 100)]
    private string $mimeType;

    #[ORM\Column(name: 'file_size', type: 'integer')]
    private int $fileSize;

    #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(name: 'artifact_id', type: 'integer')]
    private int $artifactId;

    #[ORM\ManyToOne(targetEntity: Artifact::class)]
    #[ORM\JoinColumn(name: 'artifact_id', referencedColumnName: 'id', nullable: false)]
    private Artifact $artifact;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function setStorageKey(string $storageKey): self
    {
        $this->storageKey = $storageKey;
        return $this;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function setPublicUrl(string $publicUrl): self
    {
        $this->publicUrl = $publicUrl;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getArtifactId(): int
    {
        return $this->artifactId;
    }

    public function setArtifactId(int $artifactId): self
    {
        $this->artifactId = $artifactId;
        return $this;
    }

    public function getArtifact(): Artifact
    {
        return $this->artifact;
    }

    public function setArtifact(Artifact $artifact): self
    {
        $this->artifact = $artifact;
        return $this;
    }
}
