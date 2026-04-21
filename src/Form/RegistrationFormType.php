<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your first name.']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Your first name must be at least {{ limit }} characters.',
                        'maxMessage' => 'Your first name cannot exceed {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\s\-]+$/u',
                        'message' => 'Your first name can only contain letters, spaces, and hyphens.',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your last name.']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Your last name must be at least {{ limit }} characters.',
                        'maxMessage' => 'Your last name cannot exceed {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\s\-]+$/u',
                        'message' => 'Your last name can only contain letters, spaces, and hyphens.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your email address.']),
                    new Email(['message' => 'Please enter a valid email address (e.g., name@example.com).']),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone (optional)',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 20,
                        'maxMessage' => 'Your phone number cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Account type',
                'mapped' => false,
                'choices' => [
                    'Candidat (Job Seeker)' => 'CANDIDATE',
                    'Recruteur (Company/Hiring)' => 'RECRUITER',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select an account type.']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'constraints' => [
                        new NotBlank(['message' => 'Please enter a password.']),
                        new Length([
                            'min' => 8,
                            'max' => 4096,
                            'minMessage' => 'Your password must be at least {{ limit }} characters.',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/',
                            'message' => 'Your password must contain at least one uppercase letter, one lowercase letter, and one number.',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                ],
                'invalid_message' => 'The password fields must match.',
            ])
            ->add('profilePicture', FileType::class, [
                'label' => 'Profile picture (optional)',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or WEBP).',
                        'maxSizeMessage' => 'The file is too large ({{ size }} {{ suffix }}). Maximum size is 5MB.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration_form',
            'allow_extra_fields' => true,
        ]);
    }
}