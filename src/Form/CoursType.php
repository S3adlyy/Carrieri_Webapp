<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Cours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class CoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre',
                'constraints' => [new NotBlank(['message' => 'Le titre est obligatoire'])],
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Titre du cours'],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description',
                'constraints' => [new NotBlank(['message' => 'La description est obligatoire'])],
                'attr'        => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Description du cours'],
            ])
            ->add('duree', IntegerType::class, [
                'label'       => 'Durée (heures)',
                'constraints' => [new NotBlank(), new Positive()],
                'attr'        => ['class' => 'form-control', 'min' => 1],
            ])
            ->add('niveau', ChoiceType::class, [
                'label'   => 'Niveau',
                'choices' => [
                    'Débutant'     => 'Débutant',
                    'Intermédiaire' => 'Intermédiaire',
                    'Avancé'       => 'Avancé',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('competencesVisees', TextareaType::class, [
                'label' => 'Compétences visées',
                'required' => false,
                'attr'  => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Compétences développées'],
            ])
            ->add('estObligatoire', ChoiceType::class, [
                'label'   => 'Obligatoire ?',
                'choices' => ['Oui' => 1, 'Non' => 0],
                'attr'    => ['class' => 'form-control'],
            ])
            ->add('imageCouverture', FileType::class, [
                'label'    => 'Image de couverture',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, GIF, WebP)',
                    ])
                ],
            ])
            ->add('prix', NumberType::class, [
                'label'    => 'Prix (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['class' => 'form-control', 'min' => 0, 'step' => '0.01'],
            ])
            ->add('estPayant', ChoiceType::class, [
                'label'   => 'Payant ?',
                'choices' => ['Oui' => 1, 'Non' => 0],
                'attr'    => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Cours::class]);
    }
}
