<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'feedback')]
#[ORM\Entity]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?int $note = null;

    #[ORM\Column]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?int $renduId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'rendu_id', referencedColumnName: 'id')]
    private ?RenduMission $renduMission = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(?int $note): self
    {
        $this->note = $note;
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

    public function getRenduId(): ?int
    {
        return $this->renduId;
    }

    public function setRenduId(?int $renduId): self
    {
        $this->renduId = $renduId;
        return $this;
    }

    public function getRenduMission(): ?RenduMission
    {
        return $this->renduMission;
    }

    public function setRenduMission(?RenduMission $renduMission): self
    {
        $this->renduMission = $renduMission;
        return $this;
    }
}
