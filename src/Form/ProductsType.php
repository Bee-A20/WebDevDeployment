<?php

namespace App\Form;

use App\Entity\Products;
use App\Service\ImageProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductsType extends AbstractType
{
    private ImageProvider $imageProvider;

    public function __construct(ImageProvider $imageProvider)
    {
        $this->imageProvider = $imageProvider;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $availableImages = $this->imageProvider->getAvailableImages();

        $builder
            ->add('name')
            ->add('price')
            ->add('description')
            ->add('image', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Select an image (optional)',
                'choices' => $availableImages,
                'help' => 'Choose from available product images in the Shop folder',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Products::class,
        ]);
    }
}
