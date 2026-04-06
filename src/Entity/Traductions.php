<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'traductions')]
#[ORM\Entity]
class Traductions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $hashOriginal = null;

    #[ORM\Column]
    private ?string $texteOriginal = null;

    #[ORM\Column]
    private ?string $langueCible = null;

    #[ORM\Column]
    private ?string $traduction = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateCreation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getHashOriginal(): ?string
    {
        return $this->hashOriginal;
    }

    public function setHashOriginal(?string $hashOriginal): self
    {
        $this->hashOriginal = $hashOriginal;
        return $this;
    }

    public function getTexteOriginal(): ?string
    {
        return $this->texteOriginal;
    }

    public function setTexteOriginal(?string $texteOriginal): self
    {
        $this->texteOriginal = $texteOriginal;
        return $this;
    }

    public function getLangueCible(): ?string
    {
        return $this->langueCible;
    }

    public function setLangueCible(?string $langueCible): self
    {
        $this->langueCible = $langueCible;
        return $this;
    }

    public function getTraduction(): ?string
    {
        return $this->traduction;
    }

    public function setTraduction(?string $traduction): self
    {
        $this->traduction = $traduction;
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
}
