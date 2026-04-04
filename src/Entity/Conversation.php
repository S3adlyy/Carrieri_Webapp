<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'conversation')]
#[ORM\Entity]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column]
    private ?string $dernierMessage = null;

    #[ORM\Column]
    private ?string $statut = null;

    #[ORM\Column]
    private ?int $user1Id = null;

    #[ORM\Column]
    private ?int $user2Id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user1_id', referencedColumnName: 'id')]
    private ?User $user1 = null;  // ✅ Renommé de $user à $user1

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user2_id', referencedColumnName: 'id')]
    private ?User $user2 = null;  // ✅ Renommé de $user à $user2

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDernierMessage(): ?string
    {
        return $this->dernierMessage;
    }

    public function setDernierMessage(?string $dernierMessage): self
    {
        $this->dernierMessage = $dernierMessage;
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

    public function getUser1Id(): ?int
    {
        return $this->user1Id;
    }

    public function setUser1Id(?int $user1Id): self
    {
        $this->user1Id = $user1Id;
        return $this;
    }

    public function getUser2Id(): ?int
    {
        return $this->user2Id;
    }

    public function setUser2Id(?int $user2Id): self
    {
        $this->user2Id = $user2Id;
        return $this;
    }

    // ✅ Getters et setters pour user1
    public function getUser1(): ?User
    {
        return $this->user1;
    }

    public function setUser1(?User $user1): self
    {
        $this->user1 = $user1;
        return $this;
    }

    // ✅ Getters et setters pour user2
    public function getUser2(): ?User
    {
        return $this->user2;
    }

    public function setUser2(?User $user2): self
    {
        $this->user2 = $user2;
        return $this;
    }
}