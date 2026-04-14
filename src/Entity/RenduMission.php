<?php
// src/Entity/RenduMission.php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rendu_mission')]
#[ORM\Entity]
class RenduMission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $codeSolution = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateRendu = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column(type: 'integer')]
    private ?int $missionId = null;

    #[ORM\Column(type: 'integer')]
    private ?int $candidatId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedback = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $langue = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ['default' => 'en_attente'])]
    private ?string $statut = 'en_attente';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Mission::class)]
    #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Mission $mission = null;

    /**
     * @var Collection<int, Entretien>
     */
    #[ORM\OneToMany(targetEntity: Entretien::class, mappedBy: 'rendu')]
    private Collection $entretiens;

    public function __construct()
    {
        $this->entretiens = new ArrayCollection();
    }

    // ... tous vos autres getters et setters ...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCodeSolution(): ?string
    {
        return $this->codeSolution;
    }

    public function setCodeSolution(?string $codeSolution): self
    {
        $this->codeSolution = $codeSolution;
        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): self
    {
        $this->fichier = $fichier;
        return $this;
    }

    public function getDateRendu(): ?\DateTimeInterface
    {
        return $this->dateRendu;
    }

    public function setDateRendu(?\DateTimeInterface $dateRendu): self
    {
        $this->dateRendu = $dateRendu;
        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getResultat(): ?string
    {
        return $this->resultat;
    }

    public function setResultat(?string $resultat): self
    {
        $this->resultat = $resultat;
        return $this;
    }

    public function getMissionId(): ?int
    {
        return $this->missionId;
    }

    public function setMissionId(?int $missionId): self
    {
        $this->missionId = $missionId;
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

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    public function setFeedback(?string $feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(?string $langue): self
    {
        $this->langue = $langue;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        if ($user) {
            $this->candidatId = $user->getId();
        }
        return $this;
    }

    public function getMission(): ?Mission
    {
        return $this->mission;
    }

    public function setMission(?Mission $mission): self
    {
        $this->mission = $mission;
        if ($mission) {
            $this->missionId = $mission->getId();
        }
        return $this;
    }

    /**
     * @return Collection<int, Entretien>
     */
    public function getEntretiens(): Collection
    {
        return $this->entretiens;
    }

    public function addEntretien(Entretien $entretien): self
    {
        if (!$this->entretiens->contains($entretien)) {
            $this->entretiens->add($entretien);
            $entretien->setRendu($this);
        }
        return $this;
    }

    public function removeEntretien(Entretien $entretien): self
    {
        if ($this->entretiens->removeElement($entretien)) {
            if ($entretien->getRendu() === $this) {
                $entretien->setRendu(null);
            }
        }
        return $this;
    }
}