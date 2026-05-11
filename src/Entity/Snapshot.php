<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'snapshot')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Snapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: "text")]
    private ?string $message = null;

    #[ORM\Column(type: "boolean")]
    private ?bool $isFinal = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: "integer")]
    private ?int $trackId = null;

    #[ORM\Column(type: "integer")]
    private ?int $authorId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'track_id', referencedColumnName: 'id', nullable: false)]
    private ?Track $track = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        // Set required fields
        if (!$this->title) {
            $this->title = "Snapshot";
        }

        if (!$this->createdAt) {
            $this->createdAt = new \DateTime();
        }

        if ($this->isFinal === null) {
            $this->isFinal = false;
        }

        // CRITICAL: Ensure IDs from relations are set
        if ($this->track && !$this->trackId) {
            $this->trackId = $this->track->getId();
        }

        if ($this->user && !$this->authorId) {
            $this->authorId = $this->user->getId();
        }

        // Validation - these MUST NOT be null
        if (!$this->trackId) {
            throw new \RuntimeException('trackId cannot be null');
        }

        if (!$this->authorId) {
            throw new \RuntimeException('authorId cannot be null');
        }

        if (!$this->message) {
            throw new \RuntimeException('message cannot be null');
        }
    }

    // Getters and setters
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getIsFinal(): ?bool
    {
        return $this->isFinal;
    }

    public function setIsFinal(?bool $isFinal): self
    {
        $this->isFinal = $isFinal;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
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

    public function getAuthorId(): ?int
    {
        return $this->authorId;
    }

    public function setAuthorId(?int $authorId): self
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function getTrack(): ?Track
    {
        return $this->track;
    }

    public function setTrack(?Track $track): self
    {
        $this->track = $track;
        if ($track) {
            $this->trackId = $track->getId();
        }
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        if ($user) {
            $this->authorId = $user->getId();
        }
        return $this;
    }
}