<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'offre_emploi')]
#[ORM\Entity]
class OffreEmploi
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
    private ?float $salaire = null;

    #[ORM\Column]
    private ?string $typeContrat = null;

    #[ORM\Column]
    private ?string $localisation = null;

    #[ORM\Column]
    private ?\DateTimeInterface $datePublication = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column]
    private ?string $niveauQualification = null;

    #[ORM\Column]
    private ?string $experienceRequise = null;

    #[ORM\Column]
    private ?string $competencesRequises = null;

    #[ORM\Column]
    private ?string $secteurActivite = null;

    #[ORM\Column]
    private ?string $entreprise = null;

    #[ORM\Column]
    private ?string $contactRecruteur = null;

    #[ORM\Column]
    private ?int $recruteurId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'recruteur_id', referencedColumnName: 'id')]
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

    public function getSalaire(): ?float
    {
        return $this->salaire;
    }

    public function setSalaire(?float $salaire): self
    {
        $this->salaire = $salaire;
        return $this;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(?string $typeContrat): self
    {
        $this->typeContrat = $typeContrat;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): self
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getDatePublication(): ?\DateTimeInterface
    {
        return $this->datePublication;
    }

    public function setDatePublication(?\DateTimeInterface $datePublication): self
    {
        $this->datePublication = $datePublication;
        return $this;
    }

    public function getDateExpiration(): ?\DateTimeInterface
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeInterface $dateExpiration): self
    {
        $this->dateExpiration = $dateExpiration;
        return $this;
    }

    public function getNiveauQualification(): ?string
    {
        return $this->niveauQualification;
    }

    public function setNiveauQualification(?string $niveauQualification): self
    {
        $this->niveauQualification = $niveauQualification;
        return $this;
    }

    public function getExperienceRequise(): ?string
    {
        return $this->experienceRequise;
    }

    public function setExperienceRequise(?string $experienceRequise): self
    {
        $this->experienceRequise = $experienceRequise;
        return $this;
    }

    public function getCompetencesRequises(): ?string
    {
        return $this->competencesRequises;
    }

    public function setCompetencesRequises(?string $competencesRequises): self
    {
        $this->competencesRequises = $competencesRequises;
        return $this;
    }

    public function getSecteurActivite(): ?string
    {
        return $this->secteurActivite;
    }

    public function setSecteurActivite(?string $secteurActivite): self
    {
        $this->secteurActivite = $secteurActivite;
        return $this;
    }

    public function getEntreprise(): ?string
    {
        return $this->entreprise;
    }

    public function setEntreprise(?string $entreprise): self
    {
        $this->entreprise = $entreprise;
        return $this;
    }

    public function getContactRecruteur(): ?string
    {
        return $this->contactRecruteur;
    }

    public function setContactRecruteur(?string $contactRecruteur): self
    {
        $this->contactRecruteur = $contactRecruteur;
        return $this;
    }

    public function getRecruteurId(): ?int
    {
        return $this->recruteurId;
    }

    public function setRecruteurId(?int $recruteurId): self
    {
        $this->recruteurId = $recruteurId;
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
