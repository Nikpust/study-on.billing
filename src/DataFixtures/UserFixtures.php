<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PaymentService $paymentService,
        private readonly float $initialUserBalance,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            [
                'email' => 'test-user@mail.ru',
                'password' => 'user-password',
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

            $manager->persist($user);

            $this->paymentService->depositBalance($user, $this->initialUserBalance);
        }

        $manager->flush();
    }
}
