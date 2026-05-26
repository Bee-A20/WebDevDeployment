<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter username'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Username is required',
                    ]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                    'User' => 'ROLE_USER',
                ],
                'multiple' => false,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
                'choice_attr' => fn() => ['class' => 'form-check-input'],
                'mapped' => true,
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Account Status',
                'choices' => [
                    'Active' => true,
                    'Disabled' => false,
                ],
                'multiple' => false,
                'expanded' => true,
                'required' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $options['is_edit'] ? '(Leave empty to keep current password)' : 'Enter password',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => $options['is_edit'] ? [] : [
                    new NotBlank([
                        'message' => 'Password is required for new users',
                    ]),
                ],
            ])
        ;

        // Add transformer to convert between single role string and roles array
        $builder->get('roles')->addModelTransformer(new class implements \Symfony\Component\Form\DataTransformerInterface {
            public function transform(mixed $value): mixed
            {
                // Convert array to single value, filtering out ROLE_USER
                if (is_array($value) && count($value) > 0) {
                    $filtered = array_filter($value, fn($role) => $role !== 'ROLE_USER');
                    if (!empty($filtered)) {
                        return reset($filtered);
                    }
                }
                return 'ROLE_USER';
            }

            public function reverseTransform(mixed $value): mixed
            {
                // Convert single value to array, only storing the selected role
                // ROLE_USER will be added automatically by getRoles()
                if ($value === 'ROLE_USER') {
                    return [];
                }
                return [$value];
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
        $resolver->addAllowedTypes('is_edit', 'bool');
    }
}
