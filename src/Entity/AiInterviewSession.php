<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_interview_session')]
class AiInterviewSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $candidateName = null;

    #[ORM\Column(length: 255)]
    private ?string $candidateEmail = null;

    #[ORM\Column(length: 255)]
    private ?string $position = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(type: 'json')]
    private array $questions = [];

    #[ORM\Column(type: 'json')]
    private array $responses = [];

    #[ORM\Column(type: 'json')]
    private array $evaluations = [];

    #[ORM\Column(type: 'float')]
    private float $finalScore = 0.0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $globalFeedback = null;

    #[ORM\Column(length: 20)]
    private string $status = 'en_cours';

    // Getters et Setters
    public function getId(): ?int { return $this->id; }

    public function getCandidateName(): ?string { return $this->candidateName; }
    public function setCandidateName(string $name): self { $this->candidateName = $name; return $this; }

    public function getCandidateEmail(): ?string { return $this->candidateEmail; }
    public function setCandidateEmail(string $email): self { $this->candidateEmail = $email; return $this; }

    public function getPosition(): ?string { return $this->position; }
    public function setPosition(string $position): self { $this->position = $position; return $this; }

    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(\DateTimeInterface $time): self { $this->startedAt = $time; return $this; }

    public function getEndedAt(): ?\DateTimeInterface { return $this->endedAt; }
    public function setEndedAt(?\DateTimeInterface $time): self { $this->endedAt = $time; return $this; }

    public function getQuestions(): array { return $this->questions; }
    public function setQuestions(array $questions): self { $this->questions = $questions; return $this; }

    public function getResponses(): array { return $this->responses; }
    public function setResponses(array $responses): self { $this->responses = $responses; return $this; }

    public function getEvaluations(): array { return $this->evaluations; }
    public function setEvaluations(array $evaluations): self { $this->evaluations = $evaluations; return $this; }

    public function getFinalScore(): float { return $this->finalScore; }
    public function setFinalScore(float $score): self { $this->finalScore = $score; return $this; }

    public function getGlobalFeedback(): ?string { return $this->globalFeedback; }
    public function setGlobalFeedback(?string $feedback): self { $this->globalFeedback = $feedback; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
}