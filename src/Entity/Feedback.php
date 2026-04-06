<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;


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

   #[ORM\Column(type: 'datetime_immutable')]
private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
private ?int $utilisateurId = null;

#[ORM\ManyToOne]
#[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id')]
private ?User $user = null;

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

   public function getCreatedAt(): ?\DateTimeImmutable
{
    return $this->createdAt;
}

public function setCreatedAt(?\DateTimeImmutable $createdAt): self
{
    $this->createdAt = $createdAt;
    return $this;
}

    // ========== GETTERS ET SETTERS POUR UTILISATEUR ==========

public function getUtilisateurId(): ?int
{
    return $this->utilisateurId;
}

public function setUtilisateurId(?int $utilisateurId): self
{
    $this->utilisateurId = $utilisateurId;
    return $this;
}

public function getUser(): ?User
{
    return $this->user;
}

public function setUser(?User $user): self
{
    $this->user = $user;
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
