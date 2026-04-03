<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'user')]
#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $firstName = null;

    #[ORM\Column]
    private ?string $lastName = null;

    #[ORM\Column]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $passwordHash = null;

    #[ORM\Column]
    private ?string $roles = null;

    #[ORM\Column]
    private ?int $isActive = null;

    #[ORM\Column]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?string $type = null;

    #[ORM\Column]
    private ?string $headline = null;

    #[ORM\Column]
    private ?string $bio = null;

    #[ORM\Column]
    private ?string $location = null;

    #[ORM\Column]
    private ?string $visibility = null;

    #[ORM\Column]
    private ?string $niveau = null;

    #[ORM\Column]
    private ?float $scoreGlobal = null;

    #[ORM\Column]
    private ?string $orgName = null;

    #[ORM\Column]
    private ?string $description = null;

    #[ORM\Column]
    private ?string $websiteUrl = null;

    #[ORM\Column]
    private ?string $logoUrl = null;

    #[ORM\Column]
    private ?string $profilePic = null;

    #[ORM\Column]
    private ?string $school = null;

    #[ORM\Column]
    private ?string $degree = null;

    #[ORM\Column]
    private ?string $fieldOfStudy = null;

    #[ORM\Column]
    private ?int $graduationYear = null;

    #[ORM\Column]
    private ?string $hardSkills = null;

    #[ORM\Column]
    private ?string $softSkills = null;

    #[ORM\Column]
    private ?string $githubUrl = null;

    #[ORM\Column]
    private ?string $portfolioUrl = null;

    #[ORM\Column]
    private ?string $phone = null;

    #[ORM\Column]
    private ?string $facePersonId = null;

    #[ORM\Column]
    private ?int $faceEnabled = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getRoles(): ?string
    {
        return $this->roles;
    }

    public function setRoles(?string $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getIsActive(): ?int
    {
        return $this->isActive;
    }

    public function setIsActive(?int $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
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

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): self
    {
        $this->headline = $headline;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getScoreGlobal(): ?float
    {
        return $this->scoreGlobal;
    }

    public function setScoreGlobal(?float $scoreGlobal): self
    {
        $this->scoreGlobal = $scoreGlobal;
        return $this;
    }

    public function getOrgName(): ?string
    {
        return $this->orgName;
    }

    public function setOrgName(?string $orgName): self
    {
        $this->orgName = $orgName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): self
    {
        $this->websiteUrl = $websiteUrl;
        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;
        return $this;
    }

    public function getProfilePic(): ?string
    {
        return $this->profilePic;
    }

    public function setProfilePic(?string $profilePic): self
    {
        $this->profilePic = $profilePic;
        return $this;
    }

    public function getSchool(): ?string
    {
        return $this->school;
    }

    public function setSchool(?string $school): self
    {
        $this->school = $school;
        return $this;
    }

    public function getDegree(): ?string
    {
        return $this->degree;
    }

    public function setDegree(?string $degree): self
    {
        $this->degree = $degree;
        return $this;
    }

    public function getFieldOfStudy(): ?string
    {
        return $this->fieldOfStudy;
    }

    public function setFieldOfStudy(?string $fieldOfStudy): self
    {
        $this->fieldOfStudy = $fieldOfStudy;
        return $this;
    }

    public function getGraduationYear(): ?int
    {
        return $this->graduationYear;
    }

    public function setGraduationYear(?int $graduationYear): self
    {
        $this->graduationYear = $graduationYear;
        return $this;
    }

    public function getHardSkills(): ?string
    {
        return $this->hardSkills;
    }

    public function setHardSkills(?string $hardSkills): self
    {
        $this->hardSkills = $hardSkills;
        return $this;
    }

    public function getSoftSkills(): ?string
    {
        return $this->softSkills;
    }

    public function setSoftSkills(?string $softSkills): self
    {
        $this->softSkills = $softSkills;
        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->githubUrl;
    }

    public function setGithubUrl(?string $githubUrl): self
    {
        $this->githubUrl = $githubUrl;
        return $this;
    }

    public function getPortfolioUrl(): ?string
    {
        return $this->portfolioUrl;
    }

    public function setPortfolioUrl(?string $portfolioUrl): self
    {
        $this->portfolioUrl = $portfolioUrl;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getFacePersonId(): ?string
    {
        return $this->facePersonId;
    }

    public function setFacePersonId(?string $facePersonId): self
    {
        $this->facePersonId = $facePersonId;
        return $this;
    }

    public function getFaceEnabled(): ?int
    {
        return $this->faceEnabled;
    }

    public function setFaceEnabled(?int $faceEnabled): self
    {
        $this->faceEnabled = $faceEnabled;
        return $this;
    }
}
