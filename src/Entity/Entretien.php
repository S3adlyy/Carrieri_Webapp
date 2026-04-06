<?php
// src/Entity/Entretien.php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'entretien')]
#[ORM\Entity]
class Entretien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateEntretien = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $status = 'planifie';

    #[ORM\Column(type: 'integer')]
    private ?int $postulationId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $candidat = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $candidatId = null;

    #[ORM\ManyToOne(targetEntity: RenduMission::class)]
    #[ORM\JoinColumn(name: 'rendu_id', referencedColumnName: 'id')]
    private ?RenduMission $rendu = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $renduId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDateEntretien(): ?\DateTimeInterface
    {
        return $this->dateEntretien;
    }

    public function setDateEntretien(?\DateTimeInterface $dateEntretien): self
    {
        $this->dateEntretien = $dateEntretien;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
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

    public function getPostulationId(): ?int
    {
        return $this->postulationId;
    }

    public function setPostulationId(?int $postulationId): self
    {
        $this->postulationId = $postulationId;
        return $this;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): self
    {
        $this->lien = $lien;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCandidat(): ?User
    {
        return $this->candidat;
    }

    public function setCandidat(?User $candidat): self
    {
        $this->candidat = $candidat;
        if ($candidat) {
            $this->candidatId = $candidat->getId();
        }
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

    public function getRendu(): ?RenduMission
    {
        return $this->rendu;
    }

    public function setRendu(?RenduMission $rendu): self
    {
        $this->rendu = $rendu;
        if ($rendu) {
            $this->renduId = $rendu->getId();
            $this->candidat = $rendu->getUser();
            if ($this->candidat) {
                $this->candidatId = $this->candidat->getId();
            }
        }
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
}