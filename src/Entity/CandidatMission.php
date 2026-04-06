<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'candidat_mission')]
#[ORM\Entity]
class CandidatMission
{
    #[ORM\Column]
    private ?int $candidatId = null;

    #[ORM\Column]
    private ?int $missionId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'candidat_id', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id')]
    private ?Mission $mission = null;

    public function getCandidatId(): ?int
    {
        return $this->candidatId;
    }

    public function setCandidatId(?int $candidatId): self
    {
        $this->candidatId = $candidatId;
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
