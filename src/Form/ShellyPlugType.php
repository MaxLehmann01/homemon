<?php

namespace App\Form;

use App\Entity\ShellyPlug;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ShellyPlugType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => ['placeholder' => 'z. B. Stehlampe Wohnzimmer'],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'Hostname oder IP-Adresse',
                'attr' => ['placeholder' => 'z. B. shelly-wohnzimmer.local'],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['is_edit'] ? 'Änderungen speichern' : 'Plug erstellen',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShellyPlug::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
