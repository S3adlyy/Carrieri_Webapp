<?php

declare(strict_types=1);

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
    private string $candidateName = '';

    #[ORM\Column(length: 255)]
    private string $candidateEmail = '';

    #[ORM\Column(length: 255)]
    private string $position = '';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    /** @var list<array{id:int, text:string, keywords:list<string>, maxDuration:int}> */
    #[ORM\Column(type: 'json')]
    private array $questions = [];

    /** @var list<array{questionId:int, question:string, response:string, evaluation:array<string, mixed>, timestamp:string}> */
    #[ORM\Column(type: 'json')]
    private array $responses = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $evaluations = [];

    #[ORM\Column(length: 50)]
    private string $status = 'en_cours';

    #[ORM\Column(type: 'float')]
    private float $finalScore = 0.0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $globalFeedback = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getCandidateName(): string
    {
        return $this->candidateName;
    }

    public function setCandidateName(string $candidateName): self
    {
        $this->candidateName = $candidateName;

        return $this;
    }

    public function getCandidateEmail(): string
    {
        return $this->candidateEmail;
    }

    public function setCandidateEmail(string $candidateEmail): self
    {
        $this->candidateEmail = $candidateEmail;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeInterface $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    /**
     * @return list<array{id:int, text:string, keywords:list<string>, maxDuration:int}>
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * @param list<array{id:int, text:string, keywords:list<string>, maxDuration:int}> $questions
     */
    public function setQuestions(array $questions): self
    {
        $this->questions = $questions;

        return $this;
    }

    /**
     * @return list<array{questionId:int, question:string, response:string, evaluation:array<string, mixed>, timestamp:string}>
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * @param list<array{questionId:int, question:string, response:string, evaluation:array<string, mixed>, timestamp:string}> $responses
     */
    public function setResponses(array $responses): self
    {
        $this->responses = $responses;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvaluations(): array
    {
        return $this->evaluations;
    }

    /**
     * @param array<int, array<string, mixed>> $evaluations
     */
    public function setEvaluations(array $evaluations): self
    {
        $this->evaluations = $evaluations;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFinalScore(): float
    {
        return $this->finalScore;
    }

    public function setFinalScore(float $finalScore): self
    {
        $this->finalScore = $finalScore;

        return $this;
    }

    public function getGlobalFeedback(): ?string
    {
        return $this->globalFeedback;
    }

    public function setGlobalFeedback(?string $globalFeedback): self
    {
        $this->globalFeedback = $globalFeedback;

        return $this;
    }
}
