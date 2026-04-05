<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User && $user->canAccessBackOffice()) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        if ($user instanceof User && \in_array('ROLE_CANDIDAT', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_candidate_main'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }
}
