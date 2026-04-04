<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Lecon;
use App\Entity\Module;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class LeconType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $moduleChoices = $options['module_choices'];
        $lockModule = $options['lock_module'];

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre',
                'constraints' => [new NotBlank(['message' => 'Le titre est obligatoire'])],
                'attr'        => ['class' => 'form-control', 'placeholder' => 'Titre de la leçon'],
            ])
            ->add('contenu', TextareaType::class, [
                'label'       => 'Contenu',
                'constraints' => [new NotBlank()],
                'attr'        => ['class' => 'form-control', 'rows' => 6, 'placeholder' => 'Contenu de la leçon'],
            ])
            ->add('ordre', IntegerType::class, [
                'label'       => 'Ordre',
                'constraints' => [new NotBlank(), new Positive()],
                'attr'        => ['class' => 'form-control', 'min' => 1],
            ])
            ->add('type', ChoiceType::class, [
                'label'   => 'Type',
                'choices' => [
                    'Vidéo'    => 'video',
                    'Texte'    => 'texte',
                    'Quiz'     => 'quiz',
                    'PDF'      => 'pdf',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('video', TextType::class, [
                'label'    => 'URL Vidéo',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'https://youtube.com/...'],
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lecon::class,
            'module_choices' => [],
            'lock_module' => false,
        ]);
        $resolver->setAllowedTypes('module_choices', 'array');
        $resolver->setAllowedTypes('lock_module', 'bool');
    }
}
