<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

final class ProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function isCandidate(User $user): bool
    {
        return $user->getType() === 'CANDIDATE' || in_array('ROLE_CANDIDAT', $user->getRoles(), true);
    }

    public function isRecruiter(User $user): bool
    {
        return $user->getType() === 'RECRUITER' || in_array('ROLE_RECRUITER', $user->getRoles(), true);
    }

    public function suggestPeopleYouMayKnow(User $viewer, int $limit = 5): array
    {
        if ($this->isCandidate($viewer)) {
            return $this->userRepository->findActiveSuggestionsByType($viewer->getId(), 'RECRUITER', $limit);
        }

        if ($this->isRecruiter($viewer)) {
            return $this->userRepository->findActiveSuggestionsByType($viewer->getId(), 'CANDIDATE', $limit);
        }

        return $this->userRepository->findActiveSuggestions($viewer->getId(), $limit);
    }

    public function updateBasic(User $user, array $data): void
    {
        $firstName = $this->requiredString($data['firstName'] ?? null, 'First name is required.');
        $lastName = $this->requiredString($data['lastName'] ?? null, 'Last name is required.');

        $user
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setLocation($this->nullableString($data['location'] ?? null))
            ->setPhone($this->nullableString($data['phone'] ?? null));

        if ($this->isCandidate($user)) {
            $user->setHeadline($this->nullableString($data['headline'] ?? null));
        }
    }

    public function updateAbout(User $user, ?string $bio): void
    {
        $user->setBio($this->nullableString($bio));
    }

    public function updateEducation(User $user, array $data): void
    {
        $user
            ->setSchool($this->nullableString($data['school'] ?? null))
            ->setDegree($this->nullableString($data['degree'] ?? null))
            ->setFieldOfStudy($this->nullableString($data['fieldOfStudy'] ?? null))
            ->setGraduationYear($this->parseGraduationYear($data['graduationYear'] ?? null));
    }

    public function updateSkills(User $user, array $data): void
    {
        $user
            ->setHardSkills($this->normalizeCsv($data['hardSkills'] ?? null))
            ->setSoftSkills($this->normalizeCsv($data['softSkills'] ?? null));
    }

    public function updateLinks(User $user, array $data): void
    {
        $user
            ->setGithubUrl($this->nullableString($data['githubUrl'] ?? null))
            ->setPortfolioUrl($this->nullableString($data['portfolioUrl'] ?? null));
    }

    public function updateOrganization(User $user, array $data): void
    {
        $user
            ->setOrgName($this->nullableString($data['orgName'] ?? null))
            ->setWebsiteUrl($this->nullableString($data['websiteUrl'] ?? null))
            ->setDescription($this->nullableString($data['description'] ?? null));
    }

    public function slugifyDisplayName(User $user): string
    {
        $base = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
        if ($base === '') {
            $base = $user->getEmail() ?? 'username';
        }

        $slug = strtolower($base);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'username';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'username';
    }

    private function requiredString(?string $value, string $message): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeCsv(?string $value): ?string
    {
        $items = array_filter(array_map(
            static fn ($item) => trim($item),
            explode(',', (string) $value)
        ));

        return $items === [] ? null : implode(', ', array_unique($items));
    }

    private function parseGraduationYear(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('Graduation year must be numeric.');
        }

        $year = (int) $value;
        if ($year < 1900 || $year > 2100) {
            throw new \InvalidArgumentException('Graduation year looks invalid.');
        }

        return $year;
    }
}