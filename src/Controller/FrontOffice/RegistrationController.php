<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailService;
use App\Service\FaceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SluggerInterface $slugger,
        private readonly EmailService $emailService,
        private readonly FaceRecognitionService $faceRecognitionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, SessionInterface $session): Response
    {
        // Create the form
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        // Allowed roles for display
        $allowedRoles = [
            'CANDIDATE' => 'Candidat',
            'RECRUITER' => 'Recruteur',
        ];

        // Check if we have pending registration data in session
        $pendingRegistration = $session->get('pending_registration');

        if ($pendingRegistration && $request->get('verify')) {
            // Handle verification
            return $this->verifyCode($request, $session);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Get form data
            $firstName = $form->get('firstName')->getData();
            $lastName = $form->get('lastName')->getData();
            $email = $form->get('email')->getData();
            $plainPassword = $form->get('plainPassword')->getData();
            $role = $form->get('role')->getData();
            $phone = $form->get('phone')->getData();

            // Get face image from form (if provided)
            $faceImage = $request->request->get('face_image');
            $enableFaceEnroll = $request->request->get('enable_face_enroll') === 'on';

            // Validate role is allowed
            if (!isset($allowedRoles[$role])) {
                $this->addFlash('danger', 'Invalid account type selected.');
                return $this->render('FrontOffice/security/register.html.twig', [
                    'form' => $form->createView(),
                    'allowed_roles' => $allowedRoles,
                ]);
            }

            // Check if email already exists
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('danger', 'This email is already registered.');
                return $this->render('FrontOffice/security/register.html.twig', [
                    'form' => $form->createView(),
                    'allowed_roles' => $allowedRoles,
                ]);
            }

            // Generate verification code
            $verificationCode = sprintf('%06d', random_int(0, 999999));

            // Store registration data in session temporarily
            $session->set('pending_registration', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'plainPassword' => $plainPassword,
                'role' => $role,
                'phone' => $phone,
                'verificationCode' => $verificationCode,
                'profilePicture' => null,
                'faceImage' => $faceImage,
                'enableFaceEnroll' => $enableFaceEnroll,
            ]);

            // Store profile picture if uploaded
            $profilePictureFile = $form->get('profilePicture')->getData();
            if ($profilePictureFile) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/temp';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $tempFilename = uniqid() . '.' . $profilePictureFile->guessExtension();
                $profilePictureFile->move($uploadDir, $tempFilename);
                $pendingData = $session->get('pending_registration');
                $pendingData['profilePicture'] = $tempFilename;
                $session->set('pending_registration', $pendingData);
            }

            // Send verification email
            $displayName = $firstName . ' ' . $lastName;
            $emailSent = $this->emailService->sendVerificationCode($email, $displayName, $verificationCode);

            if (!$emailSent) {
                $this->addFlash('danger', 'Failed to send verification email. Please try again.');
                return $this->render('FrontOffice/security/register.html.twig', [
                    'form' => $form->createView(),
                    'allowed_roles' => $allowedRoles,
                ]);
            }

            // Show verification form
            return $this->render('FrontOffice/security/verify_email.html.twig', [
                'email' => $email,
                'resendUrl' => $this->generateUrl('app_resend_verification'),
            ]);
        }

        // For GET request or invalid form, show the form
        return $this->render('FrontOffice/security/register.html.twig', [
            'form' => $form->createView(),
            'allowed_roles' => $allowedRoles,
        ]);
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['POST'])]
    public function verifyCode(Request $request, SessionInterface $session): Response
    {
        $verificationCode = $request->request->get('verification_code');
        $pendingData = $session->get('pending_registration');

        if (!$pendingData) {
            $this->addFlash('danger', 'No pending registration found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        // Check if verification code matches
        if ($pendingData['verificationCode'] !== $verificationCode) {
            $this->addFlash('danger', 'Invalid verification code. Please try again.');
            return $this->render('FrontOffice/security/verify_email.html.twig', [
                'email' => $pendingData['email'],
                'resendUrl' => $this->generateUrl('app_resend_verification'),
            ]);
        }

        // Create the user account
        $user = new User();
        $user->setFirstName($pendingData['firstName']);
        $user->setLastName($pendingData['lastName']);
        $user->setEmail($pendingData['email']);
        $user->setRoles($pendingData['role']);
        $user->setType($pendingData['role']);
        $user->setIsActive(1);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setFaceEnabled(0);
        $user->setIsVerified(true); // Mark as verified

        if ($pendingData['phone']) {
            $user->setPhone($pendingData['phone']);
        }

        // Set default values for required fields
        $user->setHeadline('');
        $user->setBio('');
        $user->setLocation('');
        $user->setVisibility('public');
        $user->setNiveau('');
        $user->setScoreGlobal(0);
        $user->setOrgName('');
        $user->setDescription('');
        $user->setWebsiteUrl('');
        $user->setLogoUrl('');
        $user->setSchool('');
        $user->setDegree('');
        $user->setFieldOfStudy('');
        $user->setGraduationYear(0);
        $user->setHardSkills('');
        $user->setSoftSkills('');
        $user->setGithubUrl('');
        $user->setPortfolioUrl('');
        $user->setFacePersonId('');

        // Handle profile picture
        if (isset($pendingData['profilePicture']) && $pendingData['profilePicture']) {
            $tempFile = $this->getParameter('kernel.project_dir') . '/public/uploads/temp/' . $pendingData['profilePicture'];
            $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile-pictures';

            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $targetFile = $targetDir . '/' . $pendingData['profilePicture'];
            if (file_exists($tempFile)) {
                rename($tempFile, $targetFile);
                $user->setProfilePic('/uploads/profile-pictures/' . $pendingData['profilePicture']);
            }
        } else {
            $user->setProfilePic('');
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $pendingData['plainPassword']);
        $user->setPasswordHash($hashedPassword);

        // Save user
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->logger->info('User account created', ['userId' => $user->getId(), 'email' => $user->getEmail()]);

            // Handle face enrollment after user is saved
            $faceEnrolled = false;
            if (isset($pendingData['enableFaceEnroll']) && $pendingData['enableFaceEnroll'] && !empty($pendingData['faceImage'])) {
                $this->logger->info('Attempting face enrollment for user', ['userId' => $user->getId()]);

                try {
                    $faceResult = $this->faceRecognitionService->enrollFace($user, $pendingData['faceImage']);

                    if ($faceResult['success']) {
                        $faceEnrolled = true;
                        $this->logger->info('Face enrolled successfully during registration', [
                            'userId' => $user->getId(),
                            'personId' => $faceResult['personId'] ?? 'unknown'
                        ]);
                        $this->addFlash('success', 'Face ID has been successfully enrolled!');
                    } else {
                        $this->logger->warning('Face enrollment failed during registration', [
                            'userId' => $user->getId(),
                            'error' => $faceResult['error'] ?? 'Unknown error'
                        ]);
                        $this->addFlash('warning', 'Account created but face enrollment failed: ' . ($faceResult['error'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Exception during face enrollment', [
                        'userId' => $user->getId(),
                        'error' => $e->getMessage()
                    ]);
                    $this->addFlash('warning', 'Account created but face enrollment failed. You can set it up later from your profile.');
                }
            }

            // Clear pending registration data
            $session->remove('pending_registration');

            $successMessage = 'Account created successfully! You can now log in.';
            if ($faceEnrolled) {
                $successMessage .= ' Face ID login has been enabled.';
            }
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_login');

        } catch (\Exception $e) {
            $this->logger->error('User creation failed', [
                'email' => $pendingData['email'],
                'error' => $e->getMessage()
            ]);
            $this->addFlash('danger', 'An error occurred while creating your account. Please try again.');
            return $this->redirectToRoute('app_register');
        }
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, SessionInterface $session): Response
    {
        $pendingData = $session->get('pending_registration');

        if (!$pendingData) {
            $this->addFlash('danger', 'No pending registration found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        // Generate new verification code
        $newCode = sprintf('%06d', random_int(0, 999999));
        $pendingData['verificationCode'] = $newCode;
        $session->set('pending_registration', $pendingData);

        // Resend email
        $displayName = $pendingData['firstName'] . ' ' . $pendingData['lastName'];
        $emailSent = $this->emailService->sendVerificationCode($pendingData['email'], $displayName, $newCode);

        if ($emailSent) {
            $this->addFlash('success', 'A new verification code has been sent to your email.');
        } else {
            $this->addFlash('danger', 'Failed to send verification email. Please try again.');
        }

        return $this->render('FrontOffice/security/verify_email.html.twig', [
            'email' => $pendingData['email'],
            'resendUrl' => $this->generateUrl('app_resend_verification'),
        ]);
    }
}