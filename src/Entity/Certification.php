<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'certification')]
#[ORM\Entity]
class Certification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $dateObtention = null;

    #[ORM\Column]
    private ?int $coursId = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?string $fichierPdf = null;

    #[ORM\Column]
    private ?string $cheminFichier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cours_id', referencedColumnName: 'id')]
    private ?Cours $cours = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDateObtention(): ?\DateTimeInterface
    {
        return $this->dateObtention;
    }

    public function setDateObtention(?\DateTimeInterface $dateObtention): self
    {
        $this->dateObtention = $dateObtention;
        return $this;
    }

    public function getCoursId(): ?int
    {
        return $this->coursId;
    }

    public function setCoursId(?int $coursId): self
    {
        $this->coursId = $coursId;
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

    public function getFichierPdf(): ?string
    {
        return $this->fichierPdf;
    }

    public function setFichierPdf(?string $fichierPdf): self
    {
        $this->fichierPdf = $fichierPdf;
        return $this;
    }

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(?string $cheminFichier): self
    {
        $this->cheminFichier = $cheminFichier;
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

    public function getCours(): ?Cours
    {
        return $this->cours;
    }

    public function setCours(?Cours $cours): self
    {
        $this->cours = $cours;
        return $this;
    }
}
