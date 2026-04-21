<?php

namespace App\Form;

use App\Entity\TraitementReclamation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TraitementReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailDestinataire', EmailType::class, [
                'label' => 'Email du destinataire',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'exemple@email.com'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'Veuillez entrer un email valide'])
                ],
                'required' => true,
                'mapped' => false  // Ce champ n'est pas lié à l'entité
            ])
            ->add('reponseAdmin', TextareaType::class, [
                'label' => 'Réponse à la réclamation',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Votre réponse au candidat...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La réponse est obligatoire']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La réponse doit contenir au moins {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('statutFinal', ChoiceType::class, [
                'label' => 'Statut final',
                'choices' => [
                    'Résolu' => 'Résolu',
                    'Non résolu' => 'Non résolu',
                    'En attente' => 'En attente',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le statut final est obligatoire'])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TraitementReclamation::class,
        ]);
    }
}