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
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class CoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Ex: Symfony avancé', 'maxlength' => 150],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description',
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length([
                        'min' => 10,
                        'max' => 2500,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr'        => ['class' => 'form-control', 'rows' => 4, 'maxlength' => 2500, 'placeholder' => 'Décrivez le cours en détail...'],
            ])
            ->add('duree', IntegerType::class, [
                'label'       => 'Durée (heures)',
                'invalid_message' => 'La durée doit être un nombre entier valide.',
                'constraints' => [
                    new NotBlank(['message' => 'La durée est obligatoire.']),
                    new Positive(['message' => 'La durée doit être supérieure à 0.']),
                ],
                'attr'        => ['class' => 'form-control', 'min' => 1],
            ])
            ->add('niveau', ChoiceType::class, [
                'label'   => 'Niveau',
                'placeholder' => '-- Sélectionner un niveau --',
                'choices' => [
                    'Débutant'     => 'Débutant',
                    'Intermédiaire' => 'Intermédiaire',
                    'Avancé'       => 'Avancé',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le niveau est obligatoire.']),
                    new Choice([
                        'choices' => ['Débutant', 'Intermédiaire', 'Avancé'],
                        'message' => 'Le niveau sélectionné est invalide.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('competencesVisees', TextareaType::class, [
                'label' => 'Compétences visées',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Les compétences visées sont obligatoires.']),
                    new Length([
                        'min' => 3,
                        'max' => 2500,
                        'minMessage' => 'Les compétences visées doivent contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Les compétences visées ne doivent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr'  => ['class' => 'form-control', 'rows' => 3, 'maxlength' => 2500, 'placeholder' => 'Compétences développées (ex: API REST, tests, sécurité)'],
            ])
            ->add('estObligatoire', ChoiceType::class, [
                'label'   => 'Obligatoire ?',
                'choices' => ['Oui' => 1, 'Non' => 0],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez préciser si le cours est obligatoire.']),
                    new Choice([
                        'choices' => [0, 1],
                        'message' => 'La valeur sélectionnée est invalide.',
                    ]),
                ],
                'attr'    => ['class' => 'form-control'],
            ])
            ->add('imageCouverture', FileType::class, [
                'label'    => 'Image de couverture',
                'required' => false,
                'mapped' => false,
                'data_class' => null,
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
                'invalid_message' => 'Le prix doit être un nombre valide.',
                'constraints' => [
                    new PositiveOrZero(['message' => 'Le prix doit être supérieur ou égal à 0.']),
                ],
                'attr'     => ['class' => 'form-control', 'min' => 0, 'step' => '0.01'],
            ])
            ->add('estPayant', ChoiceType::class, [
                'label'   => 'Payant ?',
                'choices' => ['Oui' => 1, 'Non' => 0],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez préciser si le cours est payant.']),
                    new Choice([
                        'choices' => [0, 1],
                        'message' => 'La valeur sélectionnée est invalide.',
                    ]),
                ],
                'attr'    => ['class' => 'form-control'],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $cours = $event->getData();

            if (!$cours instanceof Cours) {
                return;
            }

            $isPayant = (int) ($cours->getEstPayant() ?? 0) === 1;
            $prix = $cours->getPrix();

            if ($isPayant && ($prix === null || (float) $prix <= 0.0)) {
                $form->get('prix')->addError(new FormError('Le prix est obligatoire et doit être supérieur à 0 pour un cours payant.'));
            }

            if (!$isPayant && $prix !== null && (float) $prix > 0.0) {
                $form->get('prix')->addError(new FormError('Le prix doit être vide ou égal à 0 pour un cours non payant.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cours::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'cours_item',
        ]);
    }
}
