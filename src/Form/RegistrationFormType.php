<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First name',
                'constraints' => [
                    new NotBlank(['message' => 'First name is required.']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'First name must be at least {{ limit }} characters.',
                        'maxMessage' => 'First name cannot exceed {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\s\-]+$/u',
                        'message' => 'First name can only contain letters, spaces, and hyphens.',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last name',
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required.']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Last name must be at least {{ limit }} characters.',
                        'maxMessage' => 'Last name cannot exceed {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}\s\-]+$/u',
                        'message' => 'Last name can only contain letters, spaces, and hyphens.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(['message' => 'Email is required.']),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Phone (optional)',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 30,
                        'maxMessage' => 'Phone number cannot exceed {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Account type',
                'mapped' => false,
                'required' => true,
                'choices' => [
                    'Candidat' => 'CANDIDATE',
                    'Recruteur' => 'RECRUITER',
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
                        new NotBlank(['message' => 'Password is required.']),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Password must be at least {{ limit }} characters.',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                ],
                'invalid_message' => 'Passwords do not match.',
            ])
            ->add('profilePicture', FileType::class, [
                'label' => 'Profile picture (optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/jpg',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or WEBP).',
                        'maxSizeMessage' => 'The image is too large. Maximum size is 5MB.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'register',
        ]);
    }
}