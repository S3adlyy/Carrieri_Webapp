<?php
// src/Form/MissionType.php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Mission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class MissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description de la mission',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez la mission en détail...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'max' => 2000,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('scoreMin', IntegerType::class, [
                'label' => 'Score minimum requis',
                'attr' => [
                    'placeholder' => 'Ex: 75',
                    'min' => 0,
                    'max' => 100
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le score minimum est obligatoire.']),
                    new Range([
                        'min' => 0,
                        'max' => 100,
                        'notInRangeMessage' => 'Le score minimum doit être compris entre {{ min }} et {{ max }}.'
                    ])
                ]
            ])
            ->add('type', TextType::class, [
                'label' => 'Type de mission',
                'attr' => [
                    'placeholder' => 'Ex: Développement, Design, Marketing...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le type de mission est obligatoire.']),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le type doit contenir au moins {{ limit }} caractères.',
                        'max' => 100,
                        'maxMessage' => 'Le type ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mission::class,
            'csrf_protection' => true,
        ]);
    }
}