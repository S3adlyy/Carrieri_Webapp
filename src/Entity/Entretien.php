<?php

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

    #[ORM\Column]
    private ?\DateTimeInterface $dateEntretien = null;

    #[ORM\Column]
    private ?string $type = null;

    #[ORM\Column]
    private ?string $status = null;

    #[ORM\Column]
    private ?int $postulationId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'postulation_id', referencedColumnName: 'id')]
    private ?Postulation $postulation = null;

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

    public function getPostulation(): ?Postulation
    {
        return $this->postulation;
    }

    public function setPostulation(?Postulation $postulation): self
    {
        $this->postulation = $postulation;
        return $this;
    }
}
