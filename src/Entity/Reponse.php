<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'reponse')]
#[ORM\Entity]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $questionId = null;

    #[ORM\Column]
    private ?string $questionType = null;

    #[ORM\Column]
    private ?string $reponseText = null;

    #[ORM\Column]
    private ?int $estCorrecte = null;

    #[ORM\Column]
    private ?int $ordre = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getQuestionId(): ?int
    {
        return $this->questionId;
    }

    public function setQuestionId(?int $questionId): self
    {
        $this->questionId = $questionId;
        return $this;
    }

    public function getQuestionType(): ?string
    {
        return $this->questionType;
    }

    public function setQuestionType(?string $questionType): self
    {
        $this->questionType = $questionType;
        return $this;
    }

    public function getReponseText(): ?string
    {
        return $this->reponseText;
    }

    public function setReponseText(?string $reponseText): self
    {
        $this->reponseText = $reponseText;
        return $this;
    }

    public function getEstCorrecte(): ?int
    {
        return $this->estCorrecte;
    }

    public function setEstCorrecte(?int $estCorrecte): self
    {
        $this->estCorrecte = $estCorrecte;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): self
    {
        $this->ordre = $ordre;
        return $this;
    }
}
