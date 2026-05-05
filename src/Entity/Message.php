<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'message')]
#[ORM\Entity(repositoryClass: 'App\Repository\MessageRepository')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column(name: 'image_data', type: 'text', nullable: true)]
    private ?string $imageData = null;

    #[ORM\Column(name: 'file_data', type: 'text', nullable: true)]
    private ?string $fileData = null;

    #[ORM\Column(name: 'file_name', type: 'string', length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(name: 'file_size', type: 'bigint', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(name: 'file_type', type: 'string', length: 50, nullable: true)]
    private ?string $fileType = null;

    #[ORM\Column(name: 'date_envoi', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(name: 'date_modification', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'expediteur_id', referencedColumnName: 'id')]
    private ?User $expediteur = null;  // ← Nom différent : expediteur

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'destinataire_id', referencedColumnName: 'id')]
    private ?User $destinataire = null;  // ← Nom différent : destinataire

    public function __construct()
    {
        $this->dateEnvoi = new \DateTime();
        $this->statut = 'sent';
        $this->type = 'text';
    }

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

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getExpediteur(): ?User
    {
        return $this->expediteur;
    }

    public function setExpediteur(?User $expediteur): self
    {
        $this->expediteur = $expediteur;
        return $this;
    }

    public function getDestinataire(): ?User
    {
        return $this->destinataire;
    }

    public function setDestinataire(?User $destinataire): self
    {
        $this->destinataire = $destinataire;
        return $this;
    }
}
