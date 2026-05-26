<?php

namespace App\Form;

use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\Customer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrdersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a customer',
            ])
            ->add('products', EntityType::class, [
                'class' => Products::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'placeholder' => 'Select products',
                'mapped' => false, // Don't map to entity property
            ])
            ->add('quantity', null, [
                'label' => 'Quantity',
                'attr' => ['min' => 1],
                'mapped' => false, // Don't map to entity property
            ])
            ->add('createdAt', null, [
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'placeholder' => 'Select a status',
                'choices' => [
                    'Pending' => 'pending',
                    'Processing' => 'processing',
                    'Shipped' => 'shipped',
                    'Delivered' => 'delivered',
                    'Cancelled' => 'cancelled',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}