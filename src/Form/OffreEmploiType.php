<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OffreEmploi;
use App\Repository\OffreEmploiRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class OffreEmploiType extends AbstractType
{
    public function __construct(
        private OffreEmploiRepository $offreEmploiRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => "Titre de l'offre",
                'attr' => [
                    'placeholder' => 'Ex: Développeur Symfony Senior...',
                    'class' => 'form-control',
                    'data-field' => 'titre'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 100,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z]/',
                        'message' => 'Le titre ne peut pas commencer par un chiffre.',
                    ]),
                    new Callback([$this, 'validateUniqueTitre']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => "Décrivez l'offre en détail...",
                    'class' => 'form-control',
                    'data-field' => 'description'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z]/',
                        'message' => 'La description ne peut pas commencer par un chiffre.',
                    ]),
                ],
            ])
            ->add('entreprise', TextType::class, [
                'label' => 'Entreprise',
                'attr' => [
                    'placeholder' => "Nom de l'entreprise",
                    'class' => 'form-control',
                    'data-field' => 'entreprise'
                ],
                'constraints' => [
                    new NotBlank(['message' => "L'entreprise est obligatoire."]),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr' => [
                    'placeholder' => 'Ex: Tunis, Paris, Remote...',
                    'class' => 'form-control',
                    'data-field' => 'localisation'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La localisation est obligatoire.']),
                ],
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Freelance' => 'Freelance',
                ],
                'placeholder' => '-- Choisir un type --',
                'attr' => ['class' => 'form-control', 'data-field' => 'typeContrat'],
                'constraints' => [
                    new NotBlank(['message' => 'Le type de contrat est obligatoire.']),
                    new Choice([
                        'choices' => ['CDI', 'CDD', 'Stage', 'Freelance'],
                        'message' => 'Type de contrat invalide.',
                    ]),
                ],
            ])
            ->add('salaire', NumberType::class, [
                'label' => 'Salaire (DT)',
                'attr' => [
                    'placeholder' => 'Ex: 2500',
                    'class' => 'form-control',
                    'data-field' => 'salaire',
                    'step' => '0.01',
                    'min' => '0'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le salaire est obligatoire.']),
                    new Positive(['message' => 'Le salaire doit être positif.']),
                ],
            ])
            ->add('niveauQualification', TextType::class, [
                'label' => 'Niveau de qualification',
                'attr' => [
                    'placeholder' => 'Ex: Bac+3, Bac+5, Master...',
                    'class' => 'form-control',
                    'data-field' => 'niveauQualification'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le niveau de qualification est obligatoire.']),
                ],
            ])
            ->add('experienceRequise', TextType::class, [
                'label' => 'Expérience requise',
                'attr' => [
                    'placeholder' => 'Ex: 2 ans, Débutant accepté...',
                    'class' => 'form-control',
                    'data-field' => 'experienceRequise'
                ],
                'constraints' => [
                    new NotBlank(['message' => "L'expérience requise est obligatoire."]),
                ],
            ])
            ->add('competencesRequises', TextareaType::class, [
                'label' => 'Compétences requises',
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Ex: PHP, Symfony, MySQL...',
                    'class' => 'form-control',
                    'data-field' => 'competencesRequises'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Les compétences requises sont obligatoires.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'Les compétences requises doivent contenir au moins {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('secteurActivite', TextType::class, [
                'label' => "Secteur d'activité",
                'attr' => [
                    'placeholder' => 'Ex: Informatique, Finance, Santé...',
                    'class' => 'form-control',
                    'data-field' => 'secteurActivite'
                ],
                'constraints' => [
                    new NotBlank(['message' => "Le secteur d'activité est obligatoire."]),
                ],
            ])
            ->add('contactRecruteur', EmailType::class, [
                'label' => 'Email de contact',
                'attr' => [
                    'placeholder' => 'contact@entreprise.com',
                    'class' => 'form-control',
                    'data-field' => 'contactRecruteur'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le contact recruteur est obligatoire.']),
                    new Email(['message' => 'Email de contact invalide.']),
                ],
            ])
            ->add('dateExpiration', DateTimeType::class, [
                'label' => "Date et heure d'expiration",
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-field' => 'dateExpiration',
                    'min' => (new \DateTime('+1 day'))->format('Y-m-d\TH:i')
                ],
                'constraints' => [
                    new NotBlank(['message' => "La date d'expiration est obligatoire."]),
                    new GreaterThan([
                        'value' => 'now',
                        'message' => "La date d'expiration doit être dans le futur.",
                    ]),
                ],
            ]);
            
    }

    public function validateUniqueTitre($value, ExecutionContextInterface $context): void
    {
        $offre = $context->getRoot()->getData();
        $existingOffre = $this->offreEmploiRepository->findOneBy(['titre' => $value]);
        
        if ($existingOffre && $existingOffre !== $offre) {
            $context->buildViolation('Ce titre d\'offre existe déjà. Veuillez choisir un titre unique.')
                ->atPath('titre')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OffreEmploi::class,
            'csrf_protection' => true,
        ]);
    }
}