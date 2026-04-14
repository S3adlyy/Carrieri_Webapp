<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Basic Information Section
        $builder->add('firstName', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'min' => 2,
                    'max' => 50,
                    'minMessage' => 'First name must be at least {{ limit }} characters long.',
                    'maxMessage' => 'First name cannot exceed {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[\p{L}\s\-]+$/u',
                    'message' => 'First name can only contain letters, spaces, and hyphens.',
                ]),
            ],
        ]);

        $builder->add('lastName', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'min' => 2,
                    'max' => 50,
                    'minMessage' => 'Last name must be at least {{ limit }} characters long.',
                    'maxMessage' => 'Last name cannot exceed {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[\p{L}\s\-]+$/u',
                    'message' => 'Last name can only contain letters, spaces, and hyphens.',
                ]),
            ],
        ]);

        $builder->add('location', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'Location cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        $builder->add('phone', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'min' => 8,
                    'max' => 8,
                    'exactMessage' => 'Phone number must be exactly 8 digits.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^\d{8}$/',
                    'message' => 'Phone number must contain exactly 8 digits (0-9 only).',
                ]),
            ],
        ]);

        $builder->add('headline', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'Headline cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        // About Section
        $builder->add('bio', TextareaType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 5000,
                    'maxMessage' => 'Bio cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        // Education Section (these would need to be mapped to your User entity's education relationship)
        // For now assuming these are direct fields on User, but typically education would be a separate entity
        $builder->add('school', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'School name cannot exceed {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[\p{L}\s\-]+$/u',
                    'message' => 'School can only contain letters, spaces, and hyphens.',
                ]),
            ],
        ]);

        $builder->add('degree', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'Degree cannot exceed {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[\p{L}\s\-]+$/u',
                    'message' => 'Degree can only contain letters, spaces, and hyphens.',
                ]),
            ],
        ]);

        $builder->add('fieldOfStudy', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'Field of study cannot exceed {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[\p{L}\s\-]+$/u',
                    'message' => 'Field of Study can only contain letters, spaces, and hyphens.',
                ]),
            ],
        ]);

        $builder->add('graduationYear', IntegerType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Range([
                    'min' => 1900,
                    'max' => date('Y') + 10,
                    'notInRangeMessage' => 'Graduation year must be between {{ min }} and {{ max }}.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^\d{8}$/',
                    'message' => 'Year must contain exactly 8 digits (0-9 only).',
                ]),
            ],
        ]);

        // Links Section
        $builder->add('githubUrl', UrlType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Url([
                    'message' => 'Please enter a valid URL.',
                ]),
                new Assert\Length([
                    'max' => 255,
                    'maxMessage' => 'URL cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        $builder->add('portfolioUrl', UrlType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Url([
                    'message' => 'Please enter a valid URL.',
                ]),
                new Assert\Length([
                    'max' => 255,
                    'maxMessage' => 'URL cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        // Organization Section (for recruiters)
        $builder->add('orgName', TextType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 100,
                    'maxMessage' => 'Organization name cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        $builder->add('websiteUrl', UrlType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Url([
                    'message' => 'Please enter a valid URL.',
                ]),
                new Assert\Length([
                    'max' => 255,
                    'maxMessage' => 'URL cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 2000,
                    'maxMessage' => 'Description cannot exceed {{ limit }} characters.',
                ]),
            ],
        ]);

        // Skills Section
        $builder->add('hardSkills', TextareaType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 2000,
                    'maxMessage' => 'Skills list is too long.',
                ]),
            ],
        ]);

        $builder->add('softSkills', TextareaType::class, [
            'required' => false,
            'constraints' => [
                new Assert\Length([
                    'max' => 2000,
                    'maxMessage' => 'Skills list is too long.',
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
            'csrf_token_id' => 'profile_edit',
            'validation_groups' => ['Default'],
        ]);
    }
}