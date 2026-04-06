<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Table(name: 'conversation')]
#[ORM\Entity(repositoryClass: 'App\Repository\ConversationRepository')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'dernier_message', type: 'string', length: 255, nullable: true)]
    private ?string $dernierMessage = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'user1_id', type: 'integer', nullable: true)]
    private ?int $user1Id = null;

    #[ORM\Column(name: 'user2_id', type: 'integer', nullable: true)]
    private ?int $user2Id = null;

    // Relations avec les utilisateurs - NOMS DIFFÉRENTS !
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user1_id', referencedColumnName: 'id')]
    private ?User $user1 = null;  // ← Nom différent : user1

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user2_id', referencedColumnName: 'id')]
    private ?User $user2 = null;  // ← Nom différent : user2

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->statut = 'active';
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
// Ajoutez cette méthode à la fin de la classe
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function setMessages(Collection $messages): self
    {
        $this->messages = $messages;
        return $this;
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

    public function getUser1(): ?User
    {
        return $this->user1;
    }

    public function setUser1(?User $user1): self
    {
        $this->user1 = $user1;
        if ($user1) {
            $this->user1Id = $user1->getId();
        }
        return $this;
    }

    public function getUser2(): ?User
    {
        return $this->user2;
    }

    public function setUser2(?User $user2): self
    {
        $this->user2 = $user2;
        if ($user2) {
            $this->user2Id = $user2->getId();
        }
        return $this;
    }
}