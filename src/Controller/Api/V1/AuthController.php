<?php

namespace App\Controller\Api\V1;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_v1_', methods: ['POST'], format: 'json')]
final class AuthController extends AbstractController
{
    #[Route('/auth', name: 'auth')]
    #[OA\Tag(name: 'Security')]
    #[OA\Post(
        summary: 'Авторизация',
        security: []
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(
                    property: 'username',
                    type: 'string',
                    format: 'email',
                    example: 'user@example.com'
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'password123'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешная авторизация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'token',
                    type: 'string',
                    example: 'eyJ0eXAiOiJKV1QiLCJhbGciOi...'
                ),
                new OA\Property(
                    property: 'refresh_token',
                    type: 'string',
                    example: '57a804a071ddc84343ed0ab99...'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Неверные учётные данные',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'code',
                    type: 'integer',
                    example: 401
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Invalid credentials.'
                )
            ]
        )
    )]
    public function auth(): JsonResponse
    {
        throw new \LogicException('Endpoint is not callable.');
    }
}
