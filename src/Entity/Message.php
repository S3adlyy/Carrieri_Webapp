<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'message')]
#[ORM\Entity]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $contenu = null;

    #[ORM\Column]
    private ?string $imageData = null;

    #[ORM\Column]
    private ?string $fileData = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column]
    private ?string $statut = null;

    #[ORM\Column]
    private ?string $type = null;

    #[ORM\Column]
    private ?int $conversationId = null;

    #[ORM\Column]
    private ?int $expediteurId = null;

    #[ORM\Column]
    private ?int $destinataireId = null;

    #[ORM\Column]
    private ?string $fileName = null;

    #[ORM\Column]
    private ?int $fileSize = null;

    #[ORM\Column]
    private ?string $fileType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'expediteur_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'destinataire_id', referencedColumnName: 'id')]
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

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getImageData(): ?string
    {
        return $this->imageData;
    }

    public function setImageData(?string $imageData): self
    {
        $this->imageData = $imageData;
        return $this;
    }

    public function getFileData(): ?string
    {
        return $this->fileData;
    }

    public function setFileData(?string $fileData): self
    {
        $this->fileData = $fileData;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): self
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): self
    {
        $this->dateModification = $dateModification;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getConversationId(): ?int
    {
        return $this->conversationId;
    }

    public function setConversationId(?int $conversationId): self
    {
        $this->conversationId = $conversationId;
        return $this;
    }

    public function getExpediteurId(): ?int
    {
        return $this->expediteurId;
    }

    public function setExpediteurId(?int $expediteurId): self
    {
        $this->expediteurId = $expediteurId;
        return $this;
    }

    public function getDestinataireId(): ?int
    {
        return $this->destinataireId;
    }

    public function setDestinataireId(?int $destinataireId): self
    {
        $this->destinataireId = $destinataireId;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): self
    {
        $this->fileType = $fileType;
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

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
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
