<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/users', name: 'api_v1_users_', format: 'json')]
final class UserController extends AbstractController
{
    #[Route('/current', name: 'current', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Требуется авторизация.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], Response::HTTP_OK);
    }
}
