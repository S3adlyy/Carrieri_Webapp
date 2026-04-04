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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $coursChoices = $options['cours_choices'];
        $lockCours = $options['lock_cours'];

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre',
                'constraints' => [new NotBlank(['message' => 'Le titre est obligatoire'])],
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Titre du module'],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description',
                'constraints' => [new NotBlank()],
                'attr'        => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Description du module'],
            ])
            ->add('ordre', IntegerType::class, [
                'label'       => 'Ordre',
                'constraints' => [new NotBlank(), new Positive()],
                'attr'        => ['class' => 'form-control', 'min' => 1],
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
            'cours_choices' => [],
            'lock_cours' => false,
        ]);
        $resolver->setAllowedTypes('cours_choices', 'array');
        $resolver->setAllowedTypes('lock_cours', 'bool');
    }
}
