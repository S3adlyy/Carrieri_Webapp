<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Service\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('FrontOffice/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserRegistrationService $registrationService): Response
    {
        $formData = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
            'phone' => '',
            'role' => 'CANDIDATE',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token. Please try again.');

                return $this->redirectToRoute('app_register');
            }

            $formData = [
                'firstName' => (string) $request->request->get('firstName', ''),
                'lastName' => (string) $request->request->get('lastName', ''),
                'email' => (string) $request->request->get('email', ''),
                'phone' => (string) $request->request->get('phone', ''),
                'role' => (string) $request->request->get('role', 'CANDIDATE'),
            ];

            $result = $registrationService->register(
                $formData['firstName'],
                $formData['lastName'],
                $formData['email'],
                (string) $request->request->get('password'),
                (string) $request->request->get('password_confirm'),
                $formData['role'],
                $formData['phone'],
            );

            if ($result['errors'] !== []) {
                foreach ($result['errors'] as $message) {
                    $this->addFlash('danger', $message);
                }

                return $this->render('FrontOffice/security/register.html.twig', [
                    'allowed_roles' => UserRegistrationService::ALLOWED_ROLES,
                    'form_data' => $formData,
                ]);
            }

            $this->addFlash('success', 'Your account was created. You can sign in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('FrontOffice/security/register.html.twig', [
            'allowed_roles' => UserRegistrationService::ALLOWED_ROLES,
            'form_data' => $formData,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by the logout key on your firewall.');
    }
}
