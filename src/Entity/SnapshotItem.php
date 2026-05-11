<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'snapshot_item')]
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'idx_snapshot_item_file_artifact', columns: ['file_object_id', 'artifact_id'])]
class SnapshotItem
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'snapshot_id', referencedColumnName: 'id', nullable: false)]
    private ?Snapshot $snapshot = null;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'artifact_id', referencedColumnName: 'id', nullable: false)]
    private ?Artifact $artifact = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'file_object_id', referencedColumnName: 'id', nullable: true)]
    private ?FileObject $fileObject = null;

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

    public function getFileObject(): ?FileObject
    {
        return $this->fileObject;
    }

    public function setFileObject(?FileObject $fileObject): self
    {
        $this->fileObject = $fileObject;
        return $this;
    }
}