<?php
// src/Form/EntretienType.php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Entretien;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThan;

class EntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateEntretien', DateTimeType::class, [
                'label' => 'Date et heure de l\'entretien',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime('+1 day'))->format('Y-m-d\TH:i')
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de l\'entretien est obligatoire.']),
                    new GreaterThan([
                        'value' => new \DateTime('now'),
                        'message' => 'La date doit être dans le futur.'
                    ])
                ]
            ])
            ->add('type', TextType::class, [
                'label' => 'Type d\'entretien',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Technique, RH, Managerial...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le type d\'entretien est obligatoire.'])
                ]
            ])
            ->add('lien', TextType::class, [
                'label' => 'Lien de visioconférence (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://meet.google.com/...'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes supplémentaires (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Informations complémentaires pour le candidat...'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entretien::class,
            'csrf_protection' => true,
        ]);
    }
}