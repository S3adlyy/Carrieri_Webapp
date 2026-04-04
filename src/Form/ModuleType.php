<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Cours;
use App\Entity\Module;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $coursChoices = $options['cours_choices'];
        $lockCours = $options['lock_cours'];
        $includeOrdre = $options['include_ordre'];

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre',
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Length([
                        'min' => 3,
                        'max' => 150,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Ex: Introduction', 'maxlength' => 150],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description',
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 2000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr'        => ['class' => 'form-control', 'rows' => 4, 'maxlength' => 2000, 'placeholder' => 'Décrivez le module en détail...'],
            ])
            ->add('cours', EntityType::class, [
                'label'        => 'Cours',
                'class'        => Cours::class,
                'choices'      => $coursChoices,
                'choice_label' => 'titre',
                'placeholder'  => '-- Sélectionner un cours --',
                'constraints'  => [new NotBlank(['message' => 'Le cours est obligatoire'])],
                'attr'         => ['class' => 'form-control'],
                'disabled'     => $lockCours,
            ]);

        if ($includeOrdre) {
            $builder->add('ordre', IntegerType::class, [
                'label'       => 'Ordre',
                'invalid_message' => 'L\'ordre doit être un nombre entier valide.',
                'constraints' => [
                    new NotBlank(['message' => 'L\'ordre est obligatoire.']),
                    new Positive(['message' => 'L\'ordre doit être supérieur à 0.']),
                ],
                'attr'        => ['class' => 'form-control', 'min' => 1],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
            'cours_choices' => [],
            'lock_cours' => false,
            'include_ordre' => true,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'module_item',
        ]);
        $resolver->setAllowedTypes('cours_choices', 'array');
        $resolver->setAllowedTypes('lock_cours', 'bool');
        $resolver->setAllowedTypes('include_ordre', 'bool');
    }
}
