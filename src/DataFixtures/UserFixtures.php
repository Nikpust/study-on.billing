<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            [
                'email' => 'test-user@mail.ru',
                'password' => 'user-password',
                'balance' => 7250.50
            ],
            [
                'email' => 'test-admin@mail.ru',
                'password' => 'admin-password',
                'roles' => ['ROLE_SUPER_ADMIN'],
            ],
        ];

        foreach ($usersData as $userData) {
            $user = new User();

            $user->setEmail($userData['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $userData['password']));

            if (isset($userData['roles'])) {
                $user->setRoles($userData['roles']);
            }
            if (isset($userData['balance'])) {
                $user->setBalance($userData['balance']);
            }

            $manager->persist($user);
        }

        $manager->flush();
    }
}
