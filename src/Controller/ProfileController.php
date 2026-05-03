<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Track;
use App\Entity\User;
use App\Entity\Workspace;
use App\Form\ProfileForm;
use App\Service\ProfileService;
use App\Service\TrackService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\GeminiAIService;


#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileService $profileService,
        private readonly GeminiAIService $geminiAIService, // Add this
        private readonly WorkspaceService $workspaceService,
        private readonly TrackService $trackService,

    ) {
    }

    #[Route('/{id}', name: 'app_profile_view', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[Route('', name: 'app_my_profile', methods: ['GET'])]
    public function view(Request $request, ?int $id = null): Response
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
            'workspace' => $this->workspaceService->getOrCreateByUser($currentUser),
            'isOwner'   => $currentUser->getId() === $targetUser->getId(),
        ]);
    }

    #[Route('/workspace/data', name: 'workspace_data', methods: ['GET'])]
    public function workspaceData(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $workspace = $this->workspaceService->getOrCreateByUser($user);

        $tracks = $this->trackService->listByWorkspace($workspace);

        return $this->json([
            'workspace' => [
                'id' => $workspace->getId(),
                'description' => $workspace->getDescription(),
                'createdAt' => $workspace->getCreatedAt()?->format('Y-m-d H:i:s'),
                'candidateId' => $workspace->getUser(),
            ],
            'tracks' => array_map(static function (Track $track): array {
                return [
                    'id' => $track->getId(),
                    'title' => $track->getTitle(),
                    'description' => $track->getDescription(),
                    'visibility' => $track->getVisibility(),
                    'createdAt' => $track->getCreatedAt()?->format('Y-m-d H:i:s'),
                ];
            }, $tracks),
        ]);
    }

    #[Route('/workspace/track/create', name: 'workspace_track_create', methods: ['POST'])]
    public function createTrack(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $category = strtoupper(trim((string) ($payload['category'] ?? 'PROJECT')));
        $description = trim((string) ($payload['description'] ?? ''));
        $visibility = strtoupper(trim((string) ($payload['visibility'] ?? 'PRIVATE')));
        $startDate = !empty($payload['startDate']) ? new \DateTimeImmutable($payload['startDate']) : null;
        $endDate = !empty($payload['endDate']) ? new \DateTimeImmutable($payload['endDate']) : null;

        if ($title === '') {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($category, ['PROJECT', 'EXPERIENCE', 'EDUCATION', 'ACTIVITY', 'OTHER'], true)) {
            $category = 'PROJECT';
        }

        if (!in_array($visibility, ['PUBLIC', 'PRIVATE'], true)) {
            $visibility = 'PRIVATE';
        }

        $workspace = $this->workspaceService->getOrCreateByUser($user);

        $track = new Track();
        $track->setWorkspace($workspace);
        $track->setWorkspaceId($workspace->getId());
        $track->setTitle($title);
        $track->setCategory($category);
        $track->setStartDate($startDate);
        $track->setEndDate($endDate);
        $track->setDescription($description !== '' ? $description : null);
        $track->setVisibility($visibility);
        $track->setStatus('ACTIVE');
        $track->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($track);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'track' => [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'category' => $track->getCategory(),
                'startDate' => $track->getStartDate()?->format('Y-m-d'),
                'endDate' => $track->getEndDate()?->format('Y-m-d'),
                'description' => $track->getDescription(),
                'visibility' => $track->getVisibility(),
                'createdAt' => $track->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ], Response::HTTP_CREATED);
    }
    #[Route('/about/improve', name: 'app_profile_about_improve', methods: ['POST'])]
    public function improveAbout(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        try {
            // Get current bio
            $currentBio = $user->getBio() ?? '';

            if (empty(trim($currentBio))) {
                return $this->json([
                    'success' => false,
                    'error' => 'Please write something in your bio first before using AI improvement.'
                ], 400);
            }

            // Collect user data for context
            $userData = [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'headline' => $user->getHeadline(),
                'hardSkills' => $user->getHardSkills(),
                'softSkills' => $user->getSoftSkills(),
                'school' => $user->getSchool(),
                'degree' => $user->getDegree(),
                'fieldOfStudy' => $user->getFieldOfStudy(),
                'orgName' => $user->getOrgName(),
                'type' => $user->getType(),
            ];

            // Call AI service
            $improvedBio = $this->geminiAIService->improveBio($currentBio, $userData);

            return $this->json([
                'success' => true,
                'improved_bio' => $improvedBio,
                'original_bio' => $currentBio
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'AI improvement failed: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/basic/save', name: 'app_profile_basic_save', methods: ['POST'])]
    public function saveBasic(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $location = $request->request->get('location');
        $phone = $request->request->get('phone');
        $headline = $request->request->get('headline');

        $errors = [];
        $oldInput = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'location' => $location,
            'phone' => $phone,
            'headline' => $headline,
        ];

        // First Name validation
        if ($firstName !== null && trim((string) $firstName) !== '') {
            if (strlen((string) $firstName) < 2) {
                $errors['firstName'] = 'First name must be at least 2 characters long.';
            } elseif (strlen((string) $firstName) > 50) {
                $errors['firstName'] = 'First name cannot exceed 50 characters.';
            } elseif (!preg_match('/^[\p{L}\s\-]+$/u', (string) $firstName)) {
                $errors['firstName'] = 'First name can only contain letters, spaces, and hyphens.';
            }
        }

        // Last Name validation
        if ($lastName !== null && trim((string) $lastName) !== '') {
            if (strlen((string) $lastName) < 2) {
                $errors['lastName'] = 'Last name must be at least 2 characters long.';
            } elseif (strlen((string) $lastName) > 50) {
                $errors['lastName'] = 'Last name cannot exceed 50 characters.';
            } elseif (!preg_match('/^[\p{L}\s\-]+$/u', (string) $lastName)) {
                $errors['lastName'] = 'Last name can only contain letters, spaces, and hyphens.';
            }
        }

        // Phone validation (exactly 8 digits)
        if ($phone !== null && trim((string) $phone) !== '') {
            if (!preg_match('/^\d{8}$/', (string) $phone)) {
                $errors['phone'] = 'Phone number must be exactly 8 digits (0-9 only).';
            }
        }

        // Location validation
        if ($location !== null && trim((string) $location) !== '' && strlen((string) $location) > 100) {
            $errors['location'] = 'Location cannot exceed 100 characters.';
        }

        // Headline validation
        if ($headline !== null && trim((string) $headline) !== '' && strlen((string) $headline) > 100) {
            $errors['headline'] = 'Headline cannot exceed 100 characters.';
        }

        // If no errors, save and redirect
        if (empty($errors)) {
            try {
                $this->profileService->updateBasic($user, $oldInput);
                $this->entityManager->flush();
                $this->addFlash('success', 'Basic information updated successfully.');
                return $this->redirectToProfileSection('basic');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Return with errors
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => 'basic',
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
    }

    #[Route('/about/save', name: 'app_profile_about_save', methods: ['POST'])]
    public function saveAbout(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        // Check if it's an AJAX request
        $isAjax = $request->isXmlHttpRequest();

        // Verify CSRF token
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('about_form', $submittedToken)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Invalid CSRF token.'], 400);
            }
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToProfileSection('about');
        }

        $bio = $request->request->get('bio');

        if ($bio !== null) {
            try {
                $user->setBio(trim($bio));
                $this->entityManager->flush();

                if ($isAjax) {
                    return $this->json(['success' => true, 'message' => 'About section updated successfully.']);
                }

                $this->addFlash('success', 'About section updated successfully.');
                return $this->redirectToProfileSection('about');
            } catch (\Throwable $e) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
                }
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'No bio data received.'], 400);
            }
            $this->addFlash('error', 'No bio data received.');
        }

        return $this->redirectToProfileSection('about');
    }
    private function renderWithErrors(User $user, string $section, array $errors = [], array $oldInput = []): Response
    {
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => $section,
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
    }

    #[Route('/education/save', name: 'app_profile_education_save', methods: ['POST'])]
    public function saveEducation(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        $school = $request->request->get('school');
        $degree = $request->request->get('degree');
        $fieldOfStudy = $request->request->get('fieldOfStudy');
        $graduationYear = $request->request->get('graduationYear');

        $errors = [];
        $oldInput = [
            'school' => $school,
            'degree' => $degree,
            'fieldOfStudy' => $fieldOfStudy,
            'graduationYear' => $graduationYear,
        ];

        // Degree validation - only letters and spaces
        if ($degree !== null && trim((string) $degree) !== '') {
            if (!preg_match('/^[\p{L}\s\-]+$/u', (string) $degree)) {
                $errors['degree'] = 'Degree can only contain letters, spaces, and hyphens.';
            } elseif (strlen((string) $degree) > 100) {
                $errors['degree'] = 'Degree cannot exceed 100 characters.';
            }
        }

        // Field of Study validation - only letters and spaces
        if ($fieldOfStudy !== null && trim((string) $fieldOfStudy) !== '') {
            if (!preg_match('/^[\p{L}\s\-]+$/u', (string) $fieldOfStudy)) {
                $errors['fieldOfStudy'] = 'Field of study can only contain letters, spaces, and hyphens.';
            } elseif (strlen((string) $fieldOfStudy) > 100) {
                $errors['fieldOfStudy'] = 'Field of study cannot exceed 100 characters.';
            }
        }

        // School validation
        if ($school !== null && trim((string) $school) !== '' && strlen((string) $school) > 100) {
            $errors['school'] = 'School name cannot exceed 100 characters.';
        }

        // Graduation Year validation
        if ($graduationYear !== null && trim((string) $graduationYear) !== '') {
            if (!preg_match('/^\d{4}$/', (string) $graduationYear)) {
                $errors['graduationYear'] = 'Graduation year must be a valid 4-digit year.';
            } else {
                $year = (int) $graduationYear;
                $currentYear = (int) date('Y');
                if ($year < 1900 || $year > $currentYear + 10) {
                    $errors['graduationYear'] = 'Graduation year must be between 1900 and ' . ($currentYear + 10) . '.';
                }
            }
        }

        // If no errors, save and redirect
        if (empty($errors)) {
            try {
                $this->profileService->updateEducation($user, $oldInput);
                $this->entityManager->flush();
                $this->addFlash('success', 'Education updated successfully.');
                return $this->redirectToProfileSection('education');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Return with errors
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => 'education',
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
    }

    #[Route('/skills/save', name: 'app_profile_skills_save', methods: ['POST'])]
    public function saveSkills(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        $hardSkills = $request->request->get('hardSkills');
        $softSkills = $request->request->get('softSkills');

        $errors = [];
        $oldInput = [
            'hardSkills' => $hardSkills,
            'softSkills' => $softSkills,
        ];

        // Hard Skills validation
        if ($hardSkills !== null && trim((string) $hardSkills) !== '') {
            $skillsArray = preg_split('/[\r\n,]+/', (string) $hardSkills) ?: [];
            $skillsArray = array_filter(array_map('trim', $skillsArray));

            if (count($skillsArray) > 50) {
                $errors['hardSkills'] = 'You cannot add more than 50 hard skills.';
            }

            foreach ($skillsArray as $skill) {
                if (strlen($skill) > 50) {
                    $errors['hardSkills'] = 'Each skill cannot exceed 50 characters.';
                    break;
                }
            }
        }

        // Soft Skills validation
        if ($softSkills !== null && trim((string) $softSkills) !== '') {
            $skillsArray = preg_split('/[\r\n,]+/', (string) $softSkills) ?: [];
            $skillsArray = array_filter(array_map('trim', $skillsArray));

            if (count($skillsArray) > 50) {
                $errors['softSkills'] = 'You cannot add more than 50 soft skills.';
            }

            foreach ($skillsArray as $skill) {
                if (strlen($skill) > 50) {
                    $errors['softSkills'] = 'Each skill cannot exceed 50 characters.';
                    break;
                }
            }
        }

        // If no errors, save and redirect
        if (empty($errors)) {
            try {
                $processedSkills = [
                    'hardSkills' => $this->normalizeCsvTextarea($hardSkills),
                    'softSkills' => $this->normalizeCsvTextarea($softSkills),
                ];

                $this->profileService->updateSkills($user, $processedSkills);
                $this->entityManager->flush();
                $this->addFlash('success', 'Skills updated successfully.');
                return $this->redirectToProfileSection('skills');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Return with errors
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => 'skills',
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
    }

    #[Route('/links/save', name: 'app_profile_links_save', methods: ['POST'])]
    public function saveLinks(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        $githubUrl = $request->request->get('githubUrl');
        $portfolioUrl = $request->request->get('portfolioUrl');

        $errors = [];
        $oldInput = [
            'githubUrl' => $githubUrl,
            'portfolioUrl' => $portfolioUrl,
        ];

        // GitHub URL validation
        if ($githubUrl !== null && trim((string) $githubUrl) !== '') {
            $url = trim((string) $githubUrl);
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors['githubUrl'] = 'Please enter a valid GitHub URL.';
            }
        }

        // Portfolio URL validation
        if ($portfolioUrl !== null && trim((string) $portfolioUrl) !== '') {
            $url = trim((string) $portfolioUrl);
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors['portfolioUrl'] = 'Please enter a valid Portfolio URL.';
            }
        }

        // If no errors, save and redirect
        if (empty($errors)) {
            try {
                $processedLinks = [
                    'githubUrl' => $this->normalizeUrlOrNull($githubUrl),
                    'portfolioUrl' => $this->normalizeUrlOrNull($portfolioUrl),
                ];

                $this->profileService->updateLinks($user, $processedLinks);
                $this->entityManager->flush();
                $this->addFlash('success', 'Links updated successfully.');
                return $this->redirectToProfileSection('links');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Return with errors
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => 'links',
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
    }

    #[Route('/organization/save', name: 'app_profile_organization_save', methods: ['POST'])]
    public function saveOrganization(Request $request): Response
    {
        $user = $this->requireCurrentUser();

        $orgName = $request->request->get('orgName');
        $websiteUrl = $request->request->get('websiteUrl');
        $description = $request->request->get('description');

        $errors = [];
        $oldInput = [
            'orgName' => $orgName,
            'websiteUrl' => $websiteUrl,
            'description' => $description,
        ];

        // Organization Name validation
        if ($orgName !== null && trim((string) $orgName) !== '' && strlen((string) $orgName) > 100) {
            $errors['orgName'] = 'Organization name cannot exceed 100 characters.';
        }

        // Website URL validation
        if ($websiteUrl !== null && trim((string) $websiteUrl) !== '') {
            $url = trim((string) $websiteUrl);
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors['websiteUrl'] = 'Please enter a valid website URL.';
            }
        }

        // Description validation
        if ($description !== null && trim((string) $description) !== '' && strlen((string) $description) > 2000) {
            $errors['description'] = 'Description cannot exceed 2000 characters.';
        }

        // If no errors, save and redirect
        if (empty($errors)) {
            try {
                $processedData = [
                    'orgName' => $orgName,
                    'websiteUrl' => $this->normalizeUrlOrNull($websiteUrl),
                    'description' => $description,
                ];

                $this->profileService->updateOrganization($user, $processedData);
                $this->entityManager->flush();
                $this->addFlash('success', 'Organization updated successfully.');
                return $this->redirectToProfileSection('organization');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Return with errors
        $currentUser = $this->requireCurrentUser();

        return $this->render('FrontOffice/main/profile.html.twig', [
            'user' => $user,
            'is_owner' => true,
            'is_candidate' => $this->profileService->isCandidate($user),
            'is_recruiter' => $this->profileService->isRecruiter($user),
            'suggestions' => $this->profileService->suggestPeopleYouMayKnow($currentUser, 5),
            'public_profile_url' => sprintf('carrieri.app/in/%s', $this->profileService->slugifyDisplayName($user)),
            'edit_section' => 'organization',
            'form_errors' => $errors,
            'old_input' => $oldInput,
        ]);
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
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        $parts = array_map(static fn (string $item): string => trim($item), $parts);
        $parts = array_values(array_filter(array_unique($parts), static fn (string $item): bool => $item !== ''));

        if (count($parts) > 50) {
            throw new \InvalidArgumentException('You cannot have more than 50 skills.');
        }

        foreach ($parts as $skill) {
            if (strlen($skill) > 50) {
                throw new \InvalidArgumentException('Each skill cannot exceed 50 characters.');
            }
        }

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