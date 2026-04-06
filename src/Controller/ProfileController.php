<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileService $profileService,
    ) {
    }

    #[Route('/{id}', name: 'app_profile_view', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[Route('', name: 'app_my_profile', methods: ['GET'])]
    public function view(?int $id = null): Response
    {
        $currentUser = $this->requireCurrentUser();

        $targetUser = $id !== null
            ? $this->entityManager->getRepository(User::class)->find($id)
            : $currentUser;

        if (!$targetUser instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $targetUser,
            'is_owner' => $currentUser->getId() === $targetUser->getId(),
            'is_candidate' => $this->profileService->isCandidate($targetUser),
            'is_recruiter' => $this->profileService->isRecruiter($targetUser),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($targetUser)),
            'edit_section' => $this->normalizeEditSection((string) $this->getRequestEditSection()),
        ]);
    }

    #[Route('/basic/save', name: 'app_profile_basic_save', methods: ['POST'])]
    public function saveBasic(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $this->profileService->updateBasic($user, [
                'firstName' => $request->request->get('firstName'),
                'lastName' => $request->request->get('lastName'),
                'location' => $request->request->get('location'),
                'phone' => $request->request->get('phone'),
                'headline' => $request->request->get('headline'),
            ]);
            $this->entityManager->flush();
            $this->addFlash('success', 'Basic information updated successfully.');

            return $this->redirectToProfileSection('basic');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('basic');
        }
    }

    #[Route('/about/save', name: 'app_profile_about_save', methods: ['POST'])]
    public function saveAbout(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $this->profileService->updateAbout($user, $request->request->get('bio'));
            $this->entityManager->flush();
            $this->addFlash('success', 'About section updated successfully.');

            return $this->redirectToProfileSection('about');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('about');
        }
    }

    #[Route('/education/save', name: 'app_profile_education_save', methods: ['POST'])]
    public function saveEducation(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $graduationYear = $request->request->get('graduationYear');
            if ($graduationYear !== null && trim((string) $graduationYear) !== '') {
                $year = (int) $graduationYear;
                if ($year < 1900 || $year > 2100) {
                    throw new \InvalidArgumentException('Graduation year looks invalid.');
                }
            }

            $this->profileService->updateEducation($user, [
                'school' => $request->request->get('school'),
                'degree' => $request->request->get('degree'),
                'fieldOfStudy' => $request->request->get('fieldOfStudy'),
                'graduationYear' => $graduationYear,
            ]);
            $this->entityManager->flush();
            $this->addFlash('success', 'Education updated successfully.');

            return $this->redirectToProfileSection('education');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('education');
        }
    }

    #[Route('/skills/save', name: 'app_profile_skills_save', methods: ['POST'])]
    public function saveSkills(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $this->profileService->updateSkills($user, [
                'hardSkills' => $this->normalizeCsvTextarea($request->request->get('hardSkills')),
                'softSkills' => $this->normalizeCsvTextarea($request->request->get('softSkills')),
            ]);
            $this->entityManager->flush();
            $this->addFlash('success', 'Skills updated successfully.');

            return $this->redirectToProfileSection('skills');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('skills');
        }
    }

    #[Route('/links/save', name: 'app_profile_links_save', methods: ['POST'])]
    public function saveLinks(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $this->profileService->updateLinks($user, [
                'githubUrl' => $this->normalizeUrlOrNull($request->request->get('githubUrl')),
                'portfolioUrl' => $this->normalizeUrlOrNull($request->request->get('portfolioUrl')),
            ]);
            $this->entityManager->flush();
            $this->addFlash('success', 'Links updated successfully.');

            return $this->redirectToProfileSection('links');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('links');
        }
    }

    #[Route('/organization/save', name: 'app_profile_organization_save', methods: ['POST'])]
    public function saveOrganization(Request $request): RedirectResponse
    {
        $user = $this->requireCurrentUser();

        try {
            $this->profileService->updateOrganization($user, [
                'orgName' => $request->request->get('orgName'),
                'websiteUrl' => $this->normalizeUrlOrNull($request->request->get('websiteUrl')),
                'description' => $request->request->get('description'),
            ]);
            $this->entityManager->flush();
            $this->addFlash('success', 'Organization updated successfully.');

            return $this->redirectToProfileSection('organization');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToProfileSection('organization');
        }
    }

    private function requireCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $user;
    }

    private function redirectToProfileSection(string $section): RedirectResponse
    {
        return $this->redirectToRoute('app_my_profile', ['edit' => $section], Response::HTTP_SEE_OTHER);
    }

    private function normalizeCsvTextarea(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        $parts = array_map(static fn (string $item): string => trim($item), $parts);
        $parts = array_values(array_filter(array_unique($parts), static fn (string $item): bool => $item !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function normalizeUrlOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Please enter a valid URL.');
        }

        return $value;
    }

    private function getRequestEditSection(): ?string
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        return $request?->query->get('edit');
    }

    private function normalizeEditSection(string $section): ?string
    {
        $allowed = ['basic', 'about', 'education', 'skills', 'links', 'organization'];

        return in_array($section, $allowed, true) ? $section : null;
    }
}