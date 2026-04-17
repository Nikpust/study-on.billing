<?php

namespace App\Controller\Api\V1;

use App\Dto\Api\V1\RegisterUserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_v1_', methods: ['POST'], format: 'json')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
    #[Route('/register', name: 'register')]
    public function register(#[MapRequestPayload] RegisterUserDto $dto): JsonResponse
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userRoles = $user->getRoles();

        return $this->json([
            'token' => $this->JWTTokenManager->create($user),
            'roles' => $userRoles,
        ], Response::HTTP_CREATED);
    }
}
