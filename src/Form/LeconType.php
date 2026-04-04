<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Lecon;
use App\Entity\Module;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class LeconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $moduleChoices = $options['module_choices'];
        $lockModule = $options['lock_module'];
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
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Ex: Créer une route Symfony', 'maxlength' => 150],
            ])
            ->add('contenu', TextareaType::class, [
                'label'       => 'Contenu',
                'constraints' => [
                    new NotBlank(['message' => 'Le contenu est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 15000,
                        'minMessage' => 'Le contenu doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le contenu ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr'        => ['class' => 'form-control', 'rows' => 6, 'maxlength' => 15000, 'placeholder' => 'Rédigez le contenu de la leçon...'],
            ])
            ->add('video', FileType::class, [
                'label'    => 'Vidéo',
                'required' => false,
                'mapped' => false,
                'data_class' => null,
                'attr'     => ['class' => 'form-control', 'accept' => 'video/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '500M',
                        'mimeTypes' => ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm'],
                        'mimeTypesMessage' => 'Veuillez télécharger une vidéo valide (MP4, MPEG, MOV, AVI, WebM)',
                    ])
                ],
            ])
            ->add('module', EntityType::class, [
                'label'        => 'Module',
                'class'        => Module::class,
                'choices'      => $moduleChoices,
                'choice_label' => 'titre',
                'placeholder'  => '-- Sélectionner un module --',
                'constraints'  => [new NotBlank(['message' => 'Le module est obligatoire'])],
                'attr'         => ['class' => 'form-control'],
                'disabled'     => $lockModule,
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
            'data_class' => Lecon::class,
            'module_choices' => [],
            'lock_module' => false,
            'include_ordre' => true,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'lecon_item',
        ]);
        $resolver->setAllowedTypes('module_choices', 'array');
        $resolver->setAllowedTypes('lock_module', 'bool');
        $resolver->setAllowedTypes('include_ordre', 'bool');
    }
}
