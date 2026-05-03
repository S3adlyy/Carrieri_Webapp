<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'artifact')]
#[ORM\Entity]
class Artifact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $artifactName = null;

    #[ORM\Column]
    private ?string $artifactDescription = null;

    #[ORM\Column]
    private ?string $artifactType = null;

    #[ORM\Column]
    private ?string $language = null;

    #[ORM\Column]
    private ?string $testContent = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $trackId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'track_id', referencedColumnName: 'id')]
    private ?Track $track = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getArtifactName(): ?string
    {
        return $this->artifactName;
    }

    public function setArtifactName(?string $artifactName): self
    {
        $this->artifactName = $artifactName;
        return $this;
    }

    public function getArtifactDescription(): ?string
    {
        return $this->artifactDescription;
    }

    public function setArtifactDescription(?string $artifactDescription): self
    {
        $this->artifactDescription = $artifactDescription;
        return $this;
    }

    public function getArtifactType(): ?string
    {
        return $this->artifactType;
    }

    public function setArtifactType(?string $artifactType): self
    {
        $this->artifactType = $artifactType;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getTestContent(): ?string
    {
        return $this->testContent;
    }

    public function setTestContent(?string $testContent): self
    {
        $this->testContent = $testContent;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTrackId(): ?int
    {
        return $this->trackId;
    }

    public function setTrackId(?int $trackId): self
    {
        $this->trackId = $trackId;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getTrack(): ?Track
    {
        return $this->track;
    }

    public function setTrack(?Track $track): self
    {
        $this->track = $track;
        return $this;
    }
}
