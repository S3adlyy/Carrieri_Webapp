<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'progression_lecon')]
#[ORM\Entity]
class ProgressionLecon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?int $leconId = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\Column]
    private ?int $termine = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lecon_id', referencedColumnName: 'id')]
    private ?Lecon $lecon = null;

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

    public function getLeconId(): ?int
    {
        return $this->leconId;
    }

    public function setLeconId(?int $leconId): self
    {
        $this->leconId = $leconId;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): self
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getTermine(): ?int
    {
        return $this->termine;
    }

    public function setTermine(?int $termine): self
    {
        $this->termine = $termine;
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

    public function getLecon(): ?Lecon
    {
        return $this->lecon;
    }

    public function setLecon(?Lecon $lecon): self
    {
        $this->lecon = $lecon;
        return $this;
    }
}
