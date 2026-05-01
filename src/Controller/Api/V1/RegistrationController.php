<?php

namespace App\Controller\Api\V1;

use App\Dto\Api\V1\RegisterUserDto;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly PaymentService $paymentService,
        private readonly float $initialUserBalance,
    ) {
    }
    #[Route('/register', name: 'register')]
    #[OA\Tag(name: 'Security')]
    #[OA\Post(
        summary: 'Регистрация',
        security: []
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: RegisterUserDto::class))
    )]
    #[OA\Response(
        response: 201,
        description: 'Пользователь успешно создан',
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
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['ROLE_USER']
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Ошибка валидации',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    example: 'https://symfony.com/errors/validation'
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Validation Failed'
                ),
                new OA\Property(
                    property: 'status',
                    type: 'integer',
                    example: 422
                ),
                new OA\Property(
                    property: 'detail',
                    type: 'string',
                    example: 'email: Указанный email уже зарегистрирован.'
                ),
                new OA\Property(
                    property: 'violations',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'propertyPath',
                                type: 'string',
                                example: 'email'
                            ),
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Указанный email уже зарегистрирован.'
                            ),
                            new OA\Property(
                                property: 'template',
                                type: 'string',
                                example: 'Указанный email уже зарегистрирован.'
                            ),
                            new OA\Property(
                                property: 'parameters',
                                type: 'object',
                                example: [
                                '{{ value }}' => '"test-kia@mail.ru"'
                                ],
                                additionalProperties: new OA\AdditionalProperties(type: 'string')
                            ),
                            new OA\Property(
                                property: 'type',
                                type: 'string',
                                example: 'urn:uuid:23bd9dbf-6b9b-41cd-a99e-4844bcf3077f'
                            ),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )
    )]
    public function register(#[MapRequestPayload] RegisterUserDto $dto): JsonResponse
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->entityManager->persist($user);

        try {
            $this->paymentService->depositBalance($user, $this->initialUserBalance);
        } catch (\LogicException $e) {
            return $this->json([
                'message' => 'Возникла ошибка при регистрации аккаунта, обратитесь к администратору.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $user,
            30 * 24 * 3600
        );
        $this->refreshTokenManager->save($refreshToken);

        return $this->json([
            'token' => $this->JWTTokenManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }
}
