<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'cours_mission')]
#[ORM\Entity]
class CoursMission
{
    #[ORM\Column]
    private ?int $coursId = null;

    #[ORM\Column]
    private ?int $missionId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cours_id', referencedColumnName: 'id')]
    private ?Cours $cours = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id')]
    private ?Mission $mission = null;

    public function getCoursId(): ?int
    {
        return $this->coursId;
    }

    public function setCoursId(?int $coursId): self
    {
        $this->coursId = $coursId;
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

    public function getCours(): ?Cours
    {
        return $this->cours;
    }

    public function setCours(?Cours $cours): self
    {
        $this->cours = $cours;
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
