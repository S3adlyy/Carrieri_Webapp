<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'favorites_offres')]
#[ORM\Entity]
class FavoritesOffres
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?int $offreId = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'offre_id', referencedColumnName: 'id')]
    private ?OffreEmploi $offreEmploi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
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

    public function getDateAjout(): ?\DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(?\DateTimeInterface $dateAjout): self
    {
        $this->dateAjout = $dateAjout;
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
}
