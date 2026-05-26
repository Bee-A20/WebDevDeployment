<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setEmail('biangss1220@gmail.com');
        $admin->setIsVerified(true);
        $admin->setVerificationToken('');
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);
        $this->addReference('admin-user', $admin);

        $user = new User();
        $user->setUsername('bealyn');
        $user->setEmail('bealynpacunla93@gmail.com');
        $user->setIsVerified(true);
        $user->setVerificationToken('');
        // $user->setIsActive(true);
        $user->setRoles(['ROLE_USER']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
        $user->setPassword($hashedPassword);
        $manager->persist($user);

        $staff = new User();
        $staff->setUsername('staff');
        // $staff->setIsActive(true);
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setEmail('lynziepacunla@gmail.com');
        $staff->setIsVerified(true);
        $staff->setVerificationToken('');
        $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
        $staff->setPassword($hashedPassword);
        $manager->persist($staff);

        $manager->flush();
    }
}
