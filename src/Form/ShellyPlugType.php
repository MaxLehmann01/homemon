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
                'attr' => ['placeholder' => 'e.g. Living room lamp'],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'Hostname',
                'help' => 'Base URL including http:// or https://. Local hostnames and IP addresses are supported.',
                'attr' => ['placeholder' => 'http://192.168.1.42'],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['is_edit'] ? 'Save changes' : 'Add Plug',
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
