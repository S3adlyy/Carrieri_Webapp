<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    /** Role values stored in `user.roles` and `user.type` (matches your SQL dump: CANDIDATE, RECRUITER). */
    public const ALLOWED_ROLES = [
        'CANDIDATE' => 'Candidat',
        'RECRUITER' => 'Recruteur',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @return array{user: User|null, errors: list<string>}
     */
    public function register(
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        ?string $plainPassword,
        ?string $plainPasswordConfirm,
        ?string $roleKey,
        ?string $phone,
    ): array {
        $errors = [];

        $firstName = $firstName !== null ? trim($firstName) : '';
        $lastName = $lastName !== null ? trim($lastName) : '';
        $email = $email !== null ? trim(strtolower($email)) : '';
        $roleKey = $roleKey !== null ? strtoupper(trim($roleKey)) : '';
        $phone = $phone !== null ? trim($phone) : '';

        if ($firstName === '') {
            $errors[] = 'First name is required.';
        }
        if ($lastName === '') {
            $errors[] = 'Last name is required.';
        }
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if ($plainPassword === null || $plainPassword === '') {
            $errors[] = 'Password is required.';
        } elseif (\strlen($plainPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($plainPassword !== $plainPasswordConfirm) {
            $errors[] = 'Passwords do not match.';
        }
        if ($roleKey === '' || !isset(self::ALLOWED_ROLES[$roleKey])) {
            $errors[] = 'Please choose a valid account type.';
        }

        if ($email !== '' && $this->userRepository->findOneByEmail($email) !== null) {
            $errors[] = 'This email is already registered.';
        }

        if ($errors !== []) {
            return ['user' => null, 'errors' => $errors];
        }

        $user = new User();
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setRoles($roleKey);
        $user->setType($roleKey);
        $user->setIsActive(1);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setFaceEnabled(0);
        if ($phone !== '') {
            $user->setPhone($phone);
        }

        $hashed = $this->passwordHasher->hashPassword($user, (string) $plainPassword);
        $user->setPasswordHash($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ['user' => $user, 'errors' => []];
    }
}
