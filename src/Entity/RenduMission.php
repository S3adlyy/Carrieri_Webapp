<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rendu_mission')]
#[ORM\Entity]
class RenduMission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $codeSolution = null;

    #[ORM\Column]
    private ?string $fichier = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateRendu = null;

    #[ORM\Column]
    private ?float $score = null;

    #[ORM\Column]
    private ?string $resultat = null;

    #[ORM\Column]
    private ?int $missionId = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?string $feedback = null;

    #[ORM\Column]
    private ?string $langue = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id')]
    private ?Mission $mission = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getMission(): ?Mission
    {
        return $this->mission;
    }

    public function setMission(?Mission $mission): self
    {
        $this->mission = $mission;
        return $this;
    }
}
