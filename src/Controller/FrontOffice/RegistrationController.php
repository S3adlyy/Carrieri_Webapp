<?php

declare(strict_types=1);

namespace App\Controller\FrontOffice;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailService;
use App\Service\AwsFaceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormError;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailService $emailService,
        private readonly AwsFaceRecognitionService $awsFaceService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, SessionInterface $session): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        $allowedRoles = [
            'CANDIDATE' => 'Candidat',
            'RECRUITER' => 'Recruteur',
        ];

        // Debug logging - moved to correct location
        if ($form->isSubmitted()) {
            $this->logger->debug('Form was submitted');

            if (!$form->isValid()) {
                $this->logger->debug('Form validation FAILED');
                // Log all form errors
                foreach ($form->getErrors(true) as $error) {
                    if ($error instanceof FormError) {
                        $this->logger->debug('Form error: ' . $error->getMessage());
                    }
                }
                // Log field errors
                foreach ($form->all() as $child) {
                    foreach ($child->getErrors() as $error) {
                        if ($error instanceof FormError) {
                            $this->logger->debug('Field ' . $child->getName() . ' error: ' . $error->getMessage());
                        }
                    }
                }
            } else {
                $this->logger->debug('Form validation PASSED');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $firstName = $form->get('firstName')->getData();
            $lastName = $form->get('lastName')->getData();
            $email = $form->get('email')->getData();
            $plainPassword = $form->get('plainPassword')->getData();
            $role = $form->get('role')->getData();
            $phone = $form->get('phone')->getData();

            $faceImage = $request->request->get('face_image');
            $faceImageString = is_string($faceImage) ? $faceImage : '';
            $this->logger->info('Registration data - RAW', [
                'has_face_image' => $request->request->has('face_image'),
                'face_image_length' => strlen($faceImageString),
                'all_post_keys' => array_keys($request->request->all())
            ]);
            $enableFaceEnroll = $request->request->get('enable_face_enroll');
            $enableFaceEnrollString = is_string($enableFaceEnroll) ? $enableFaceEnroll : '0';

            $this->logger->info('Registration data', [
                'has_face_image' => !empty($faceImageString),
                'face_image_length' => strlen($faceImageString),
                'enable_face_enroll' => $enableFaceEnrollString
            ]);

            if (!isset($allowedRoles[$role])) {
                $this->addFlash('danger', 'Invalid account type selected.');
                return $this->render('FrontOffice/security/register.html.twig', [
                    'form' => $form->createView(),
                    'allowed_roles' => $allowedRoles,
                ]);
            }

            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('danger', 'This email is already registered.');
                return $this->render('FrontOffice/security/register.html.twig', [
                    'form' => $form->createView(),
                    'allowed_roles' => $allowedRoles,
                ]);
            }

            $verificationCode = sprintf('%06d', random_int(0, 999999));

            // Save face image to temporary file instead of session
            $tempFaceImagePath = null;
            if ($enableFaceEnrollString === '1' && !empty($faceImageString)) {
                $projectDir = $this->getParameter('kernel.project_dir');
                if (!is_string($projectDir)) {
                    $projectDir = '';
                }
                $tempDir = $projectDir . '/public/uploads/temp_faces';
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0777, true);
                }
                $tempFaceImagePath = $tempDir . '/face_' . uniqid() . '.txt';
                file_put_contents($tempFaceImagePath, $faceImageString);
                $this->logger->info('Face image saved to temp file', ['path' => $tempFaceImagePath]);
            }

            // Store registration data in session (without the large face image)
            $session->set('pending_registration', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'plainPassword' => $plainPassword,
                'role' => $role,
                'phone' => $phone,
                'verificationCode' => $verificationCode,
                'profilePicture' => null,
                'enableFaceEnroll' => $enableFaceEnrollString,
                'tempFaceImagePath' => $tempFaceImagePath,
            ]);

            // Store profile picture if uploaded
            $profilePictureFile = $form->get('profilePicture')->getData();
            if ($profilePictureFile) {
                $projectDir = $this->getParameter('kernel.project_dir');
                if (!is_string($projectDir)) {
                    $projectDir = '';
                }
                $uploadDir = $projectDir . '/public/uploads/temp';
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

            return $this->render('FrontOffice/security/verify_email.html.twig', [
                'email' => $email,
                'resendUrl' => $this->generateUrl('app_resend_verification'),
            ]);
        }

        // If form is submitted but invalid, or just displaying the form
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

        $this->logger->info('Verification attempt', [
            'code_submitted' => $verificationCode,
            'has_session' => $pendingData !== null,
            'session_keys' => is_array($pendingData) ? array_keys($pendingData) : []
        ]);

        if (!$pendingData || !is_array($pendingData)) {
            $this->addFlash('danger', 'No pending registration found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        /** @var array{firstName:mixed,lastName:mixed,email:mixed,plainPassword:mixed,role:mixed,phone?:mixed,verificationCode:mixed,profilePicture?:mixed,enableFaceEnroll:mixed,tempFaceImagePath?:mixed} $pendingData */

        $verificationCodeString = is_string($verificationCode) ? $verificationCode : '';
        if ((string)$pendingData['verificationCode'] !== $verificationCodeString) {
            $this->addFlash('danger', 'Invalid verification code. Please try again.');
            return $this->render('FrontOffice/security/verify_email.html.twig', [
                'email' => $pendingData['email'],
                'resendUrl' => $this->generateUrl('app_resend_verification'),
            ]);
        }

        // Create user
        $user = new User();
        $user->setFirstName((string) $pendingData['firstName']);
        $user->setLastName((string) $pendingData['lastName']);
        $user->setEmail((string) $pendingData['email']);
        $role = $pendingData['role'];
        $user->setRoles($role);
        $user->setType($role);
        $user->setIsActive(1);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setFaceEnabled(0);
        $user->setIsVerified(true);

        if (!empty($pendingData['phone'])) {
            $user->setPhone((string) $pendingData['phone']);
        }

        // Set default values
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
            $projectDir = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDir)) {
                $projectDir = '';
            }
            $profilePicture = (string) $pendingData['profilePicture'];
            $tempFile = $projectDir . '/public/uploads/temp/' . $profilePicture;
            $targetDir = $projectDir . '/public/uploads/profile-pictures';

            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $targetFile = $targetDir . '/' . $profilePicture;
            if (file_exists($tempFile)) {
                rename($tempFile, $targetFile);
                $user->setProfilePic('/uploads/profile-pictures/' . $profilePicture);
            }
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $pendingData['plainPassword']);
        $user->setPasswordHash($hashedPassword);
        echo "hashed password: ".$hashedPassword;

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->logger->info('User saved', ['userId' => $user->getId()]);

            // Handle face enrollment - read from temp file
            $enableFaceEnroll = is_string($pendingData['enableFaceEnroll'] ?? '0') ? $pendingData['enableFaceEnroll'] : '0';
            $faceImage = null;

            if ($enableFaceEnroll === '1' && isset($pendingData['tempFaceImagePath'])) {
                $tempFacePath = $pendingData['tempFaceImagePath'];
                if (is_string($tempFacePath) && file_exists($tempFacePath)) {
                    $faceImage = file_get_contents($tempFacePath);
                    $this->logger->info('Face image loaded from temp file', [
                        'path' => $tempFacePath,
                        'length' => is_string($faceImage) ? strlen($faceImage) : 0
                    ]);
                    // Clean up temp file
                    unlink($tempFacePath);
                } else {
                    $this->logger->warning('Temp face file not found', ['path' => $tempFacePath]);
                }
            }

            $faceImageString = is_string($faceImage) ? $faceImage : '';
            $this->logger->info('Face enrollment check', [
                'enable' => $enableFaceEnroll,
                'has_image' => !empty($faceImageString),
                'image_length' => strlen($faceImageString)
            ]);

            if ($enableFaceEnroll === '1' && !empty($faceImageString)) {
                $userId = $user->getId();
                if ($userId) {
                    $this->logger->info('Calling face enrollment', ['userId' => $userId]);
                }

                // Clean the face image
                $cleanFaceImage = preg_replace('/^data:image\/\w+;base64,/', '', $faceImageString);
                $cleanFaceImageString = is_string($cleanFaceImage) ? $cleanFaceImage : '';
                $faceResult = $this->awsFaceService->enrollFace($user, $cleanFaceImageString);

                if ($faceResult['success']) {
                    $this->logger->info('Face enrollment SUCCESS', ['faceId' => $faceResult['faceId'] ?? '']);
                    $this->addFlash('success', 'Face ID has been enabled!');
                } else {
                    $errorMessage = (string) ($faceResult['error'] ?? 'Unknown error');
                    $this->logger->error('Face enrollment FAILED', ['error' => $errorMessage]);
                    $this->addFlash('warning', 'Face enrollment failed: ' . $errorMessage);
                }
            }

            // Clear session
            $session->remove('pending_registration');

            $this->addFlash('success', 'Account created successfully! You can now log in.');
            return $this->redirectToRoute('app_login');

        } catch (\Exception $e) {
            $this->logger->error('User creation failed', ['error' => $e->getMessage()]);
            $this->addFlash('danger', 'Error: ' . $e->getMessage());
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

        $newCode = sprintf('%06d', random_int(0, 999999));
        $pendingData['verificationCode'] = $newCode;
        $session->set('pending_registration', $pendingData);

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