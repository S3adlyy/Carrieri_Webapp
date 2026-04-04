<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cours')]
#[ORM\Entity]
class Cours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $titre = null;

    #[ORM\Column]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $duree = null;

    #[ORM\Column]
    private ?string $niveau = null;

    #[ORM\Column]
    private ?string $competencesVisees = null;

    #[ORM\Column]
    private ?int $estObligatoire = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private mixed $imageCouverture = null;

    #[ORM\Column]
    private ?float $prix = null;

    #[ORM\Column]
    private ?int $estPayant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): self
    {
        $this->titre = $titre;
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

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(?int $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getCompetencesVisees(): ?string
    {
        return $this->competencesVisees;
    }

    public function setCompetencesVisees(?string $competencesVisees): self
    {
        $this->competencesVisees = $competencesVisees;
        return $this;
    }

    public function getEstObligatoire(): ?int
    {
        return $this->estObligatoire;
    }

    public function setEstObligatoire(?int $estObligatoire): self
    {
        $this->estObligatoire = $estObligatoire;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getImageCouverture(): mixed
    {
        return $this->imageCouverture;
    }

    public function setImageCouverture(mixed $imageCouverture): self
    {
        $this->imageCouverture = $imageCouverture;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getEstPayant(): ?int
    {
        return $this->estPayant;
    }

    public function setEstPayant(?int $estPayant): self
    {
        $this->estPayant = $estPayant;
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
}
