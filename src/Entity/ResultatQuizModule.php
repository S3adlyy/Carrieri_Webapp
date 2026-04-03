<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'resultat_quiz_module')]
#[ORM\Entity]
class ResultatQuizModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?int $moduleId = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column]
    private ?int $totalPoints = null;

    #[ORM\Column]
    private ?\DateTimeInterface $dateCompletion = null;

    #[ORM\Column]
    private ?int $reussite = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    private ?Module $module = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
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

    public function getModuleId(): ?int
    {
        return $this->moduleId;
    }

    public function setModuleId(?int $moduleId): self
    {
        $this->moduleId = $moduleId;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(?int $totalPoints): self
    {
        $this->totalPoints = $totalPoints;
        return $this;
    }

    public function getDateCompletion(): ?\DateTimeInterface
    {
        return $this->dateCompletion;
    }

    public function setDateCompletion(?\DateTimeInterface $dateCompletion): self
    {
        $this->dateCompletion = $dateCompletion;
        return $this;
    }

    public function getReussite(): ?int
    {
        return $this->reussite;
    }

    public function setReussite(?int $reussite): self
    {
        $this->reussite = $reussite;
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

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): self
    {
        $this->module = $module;
        return $this;
    }
}
