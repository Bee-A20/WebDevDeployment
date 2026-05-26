<?php

namespace App\DataFixtures;

use App\Entity\Products;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get admin user from UserFixtures
        $admin = $this->getReference('admin-user', User::class);

        // Sample products
        $products = [
            [
                'name' => 'Ballpoint Pen',
                'price' => '2.50',
                'description' => 'Classic ballpoint pen with smooth writing experience',
                'stock' => 200,
                'image' => 'ballpoint-pen.jpg',
            ],
            [
                'name' => 'Highlighter Set 3 Colors',
                'price' => '5.99',
                'description' => 'Set of 3 bright highlighters - yellow, pink, and blue',
                'stock' => 150,
                'image' => 'highlighter-set.jpg',
            ],
            [
                'name' => 'Pastel Aesthetic Notebook',
                'price' => '8.75',
                'description' => 'Beautiful pastel aesthetic notebook perfect for notes and journaling',
                'stock' => 120,
                'image' => 'pastel-notebook.jpg',
            ],
        ];

        foreach ($products as $productData) {
            $product = new Products();
            $product->setName($productData['name']);
            $product->setPrice($productData['price']);
            $product->setDescription($productData['description']);
            $product->setStock($productData['stock']);
            $product->setImage($productData['image']);
            $product->setCreatedBy($admin);

            $manager->persist($product);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
