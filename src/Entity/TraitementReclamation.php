<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'traitement_reclamation')]
#[ORM\Entity]
class TraitementReclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateTraitement = null;

    #[ORM\Column]
    private ?string $reponseAdmin = null;

    #[ORM\Column]
    private ?string $statutFinal = null;

    #[ORM\Column]
    private ?int $reclamationId = null;

    #[ORM\Column]
    private ?int $adminId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'reclamation_id', referencedColumnName: 'id')]
    private ?Reclamation $reclamation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id')]
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

    public function getDateTraitement(): ?\DateTimeInterface
    {
        return $this->dateTraitement;
    }

    public function setDateTraitement(?\DateTimeInterface $dateTraitement): self
    {
        $this->dateTraitement = $dateTraitement;
        return $this;
    }

    public function getReponseAdmin(): ?string
    {
        return $this->reponseAdmin;
    }

    public function setReponseAdmin(?string $reponseAdmin): self
    {
        $this->reponseAdmin = $reponseAdmin;
        return $this;
    }

    public function getStatutFinal(): ?string
    {
        return $this->statutFinal;
    }

    public function setStatutFinal(?string $statutFinal): self
    {
        $this->statutFinal = $statutFinal;
        return $this;
    }

    public function getReclamationId(): ?int
    {
        return $this->reclamationId;
    }

    public function setReclamationId(?int $reclamationId): self
    {
        $this->reclamationId = $reclamationId;
        return $this;
    }

    public function getAdminId(): ?int
    {
        return $this->adminId;
    }

    public function setAdminId(?int $adminId): self
    {
        $this->adminId = $adminId;
        return $this;
    }

    public function getReclamation(): ?Reclamation
    {
        return $this->reclamation;
    }

    public function setReclamation(?Reclamation $reclamation): self
    {
        $this->reclamation = $reclamation;
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
