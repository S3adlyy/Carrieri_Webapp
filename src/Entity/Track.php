<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'track')]
#[ORM\Entity]
class Track
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $title = null;

    #[ORM\Column]
    private ?string $description = null;

    #[ORM\Column]
    private ?string $category = null;

    #[ORM\Column]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?string $visibility = null;

    #[ORM\Column]
    private ?int $workspaceId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'workspace_id', referencedColumnName: 'id')]
    private ?Workspace $workspace = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getWorkspaceId(): ?int
    {
        return $this->workspaceId;
    }

    public function setWorkspaceId(?int $workspaceId): self
    {
        $this->workspaceId = $workspaceId;
        return $this;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): self
    {
        $this->workspace = $workspace;
        return $this;
    }
}
