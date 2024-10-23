<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Enter Product Name',
                ]
            ])
            ->add('price', NumberType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Enter Product Price',
                ]
            ])
            ->add('stock', NumberType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Enter Product Stock',
                ]
            ])
            ->add('description', TextareaType::class, [
                "label" => false,
                'attr' => [
                    'placeholder' => 'Enter Product Description',
                    'rows' => 10
                ]
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
