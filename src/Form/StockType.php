<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Products;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Products::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-control'],
                'label' => 'Product',
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 1],
                'label' => 'Quantity to Add',
            ])
            ->add('notes', TextareaType::class, [
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'required' => false,
                'label' => 'Notes',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
