<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Trait for casting UserInterface to App\Entity\User
 * This helps when controllers need to access User-specific methods.
 */
trait UserTypeCasterTrait
{
    /**
     * Cast UserInterface to User, returning null if not an instance of User.
     */
    protected function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    /**
     * Cast UserInterface to User, throwing exception if not an instance of User.
     */
    protected function requireAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Expected App\Entity\User instance');
        }
        return $user;
    }

    /**
     * Get user ID safely, returns null if not authenticated.
     */
    protected function getUserId(): ?int
    {
        $user = $this->getAuthenticatedUser();
        return $user?->getId();
    }

    /**
     * Get user type safely, returns null if not authenticated or type not set.
     */
    protected function getUserType(): ?string
    {
        $user = $this->getAuthenticatedUser();
        return $user?->getType();
    }
}

