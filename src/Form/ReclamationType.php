<?php

namespace App\Form\FrontOffice;

use App\Entity\Cours;
use App\Entity\Reclamation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('objet', TextType::class, [
                'label' => 'Objet de la réclamation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Problème avec le cours...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'objet est obligatoire']),
                    new Length([
                        'min' => 5,
                        'minMessage' => 'L\'objet doit contenir au moins {{ limit }} caractères',
                        'max' => 255,
                        'maxMessage' => 'L\'objet ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre problème en détail...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('cours', EntityType::class, [
                'class' => Cours::class,
                'choice_label' => 'titre',
                'label' => 'Cours concerné',
                'attr' => ['class' => 'form-control'],
                'required' => true,
                'placeholder' => '-- Sélectionnez un cours --',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un cours'])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
            'csrf_protection' => true,
        ]);
    }
}