<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'snapshot_item')]
#[ORM\Entity]
class SnapshotItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $snapshotId = null;

    #[ORM\Column]
    private ?int $artifactId = null;

    #[ORM\Column]
    private ?int $fileObjectId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'snapshot_id', referencedColumnName: 'id')]
    private ?Snapshot $snapshot = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'artifact_id', referencedColumnName: 'id')]
    private ?Artifact $artifact = null;

    // ✅ Une seule déclaration de $fileObject (supprimez la ligne 30)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'file_object_id', referencedColumnName: 'id')]
    private ?FileObject $fileObject = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getSnapshotId(): ?int
    {
        return $this->snapshotId;
    }

    public function setSnapshotId(?int $snapshotId): self
    {
        $this->snapshotId = $snapshotId;
        return $this;
    }

    public function getArtifactId(): ?int
    {
        return $this->artifactId;
    }

    public function setArtifactId(?int $artifactId): self
    {
        $this->artifactId = $artifactId;
        return $this;
    }

    public function getFileObjectId(): ?int
    {
        return $this->fileObjectId;
    }

    public function setFileObjectId(?int $fileObjectId): self
    {
        $this->fileObjectId = $fileObjectId;
        return $this;
    }

    public function getSnapshot(): ?Snapshot
    {
        return $this->snapshot;
    }

    public function setSnapshot(?Snapshot $snapshot): self
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getArtifact(): ?Artifact
    {
        return $this->artifact;
    }

    public function setArtifact(?Artifact $artifact): self
    {
        $this->artifact = $artifact;
        return $this;
    }

    // ✅ Une seule méthode getFileObject()
    public function getFileObject(): ?FileObject
    {
        return $this->fileObject;
    }

    // ✅ Une seule méthode setFileObject()
    public function setFileObject(?FileObject $fileObject): self
    {
        $this->fileObject = $fileObject;
        return $this;
    }
}