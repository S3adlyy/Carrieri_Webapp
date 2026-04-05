<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'postulation')]
#[ORM\Entity]
class Postulation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePostulation = null;

    #[ORM\Column]
    private ?string $statut = null;

    #[ORM\Column]
    private ?string $motivationCandidature = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?int $offreId = null;

    #[ORM\Column]
    private ?string $cvPath = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'offre_id', referencedColumnName: 'id')]
    private ?OffreEmploi $offreEmploi = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
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

    public function getDatePostulation(): ?\DateTimeInterface
    {
        return $this->datePostulation;
    }

    public function setDatePostulation(?\DateTimeInterface $datePostulation): self
    {
        $this->datePostulation = $datePostulation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getMotivationCandidature(): ?string
    {
        return $this->motivationCandidature;
    }

    public function setMotivationCandidature(?string $motivationCandidature): self
    {
        $this->motivationCandidature = $motivationCandidature;
        return $this;
    }

    public function getCandidatId(): ?int
    {
        return $this->candidatId;
    }

    public function setCandidatId(?int $candidatId): self
    {
        $this->candidatId = $candidatId;
        return $this;
    }

    public function getOffreId(): ?int
    {
        return $this->offreId;
    }

    public function setOffreId(?int $offreId): self
    {
        $this->offreId = $offreId;
        return $this;
    }

    public function getCvPath(): ?string
    {
        return $this->cvPath;
    }

    public function setCvPath(?string $cvPath): self
    {
        $this->cvPath = $cvPath;
        return $this;
    }

    public function getOffreEmploi(): ?OffreEmploi
    {
        return $this->offreEmploi;
    }

    public function setOffreEmploi(?OffreEmploi $offreEmploi): self
    {
        $this->offreEmploi = $offreEmploi;
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
