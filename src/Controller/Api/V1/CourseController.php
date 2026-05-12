<?php

namespace App\Controller\Api\V1;

use App\Dto\Api\V1\CourseRequestDto;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/courses', name: 'api_v1_courses_', format: 'json')]
final class CourseController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly PaymentService $paymentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(methods: ['GET'])]
    #[OA\Tag(name: 'Courses')]
    #[OA\Get(
        summary: 'Список курсов',
        security: []
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Список курсов',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(
                        property: 'code',
                        type: 'string',
                        example: 'symfony-basics'
                    ),
                    new OA\Property(
                        property: 'title',
                        type: 'string',
                        example: 'Основы Symfony'
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        example: 'rent',
                        enum: ['rent', 'buy', 'free']
                    ),
                    new OA\Property(
                        property: 'price',
                        type: 'string',
                        example: '399.90',
                        nullable: true
                    ),
                ],
                type: 'object'
            )
        )
    )]
    public function getCourses(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();

        $normalizedCourses = [];
        foreach ($courses as $course) {
            $normalizedCourses[] = $this->normalizeCourseData($course);
        }

        return $this->json(
            $normalizedCourses,
            Response::HTTP_OK
        );
    }

    #[Route(name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Tag(name: 'Courses')]
    #[OA\Post(
        summary: 'Создание курса',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['type', 'title', 'code'],
            properties: [
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    example: 'rent',
                    enum: ['rent', 'buy', 'free']
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Основы Symfony'
                ),
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: 'symfony-basics'
                ),
                new OA\Property(
                    property: 'price',
                    type: 'number',
                    format: 'float',
                    example: 399.90,
                    nullable: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Курс успешно создан',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Курс с таким кодом уже существует',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Курс с указанным кодом уже существует в системе.'
                ),
            ],
            type: 'object'
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
                    example: 'code: Код курса должен содержать не более 255 символов.'
                ),
                new OA\Property(
                    property: 'violations',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'propertyPath',
                                type: 'string',
                                example: 'code'
                            ),
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Код курса должен содержать не более 255 символов.'
                            ),
                            new OA\Property(
                                property: 'template',
                                type: 'string',
                                example: 'Код курса должен содержать не более 255 символов.'
                            ),
                            new OA\Property(
                                property: 'parameters',
                                type: 'object',
                                example: [
                                    '{{ max }}' => '255',
                                    '{{ value }}' => '"sy"',
                                    '{{ limit }}' => '3',
                                    '{{ min }}' => '3',
                                    '{{ value_length }}' => '2'
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
    public function createCourse(#[MapRequestPayload] CourseRequestDto $dto): JsonResponse
    {
        if ($this->courseRepository->findOneBy(['code' => $dto->code]) !== null) {
            return $this->json([
                'message' => 'Курс с указанным кодом уже существует в системе.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $course = new Course();
        $course->setCode($dto->code);
        $course->setTitle($dto->title);
        $course->setType(CourseTypeEnum::fromCode($dto->type));
        if (CourseTypeEnum::fromCode($dto->type) !== CourseTypeEnum::FREE) {
            $course->setPrice($dto->price);
        }

        $this->entityManager->persist($course);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
        ], Response::HTTP_CREATED);
    }

    #[Route('/{code}', name: 'show', methods: ['GET'])]
    #[OA\Tag(name: 'Courses')]
    #[OA\Get(
        summary: 'Получение курса',
        security: []
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Символьный код курса',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            example: 'symfony-basics'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Данные курса',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: 'symfony-basics'
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Основы Symfony'
                ),
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    example: 'buy',
                    enum: ['rent', 'buy', 'free']
                ),
                new OA\Property(
                    property: 'price',
                    type: 'string',
                    example: '399.90',
                    nullable: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Курс не найден',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Курс не найден.'
                ),
            ],
            type: 'object'
        )
    )]
    public function getCourseByCode(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return $this->json([
                'message' => 'Курс не найден.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->normalizeCourseData($course),
            Response::HTTP_OK
        );
    }

    #[Route('/{code}', name: 'edit', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[OA\Tag(name: 'Courses')]
    #[OA\Post(
        summary: 'Редактирование курса',
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Символьный код курса',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            example: 'symfony-basics'
        )
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['type', 'title', 'code'],
            properties: [
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    example: 'rent',
                    enum: ['rent', 'buy', 'free']
                ),
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'Основы Symfony'
                ),
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: 'symfony-basics'
                ),
                new OA\Property(
                    property: 'price',
                    type: 'number',
                    format: 'float',
                    example: 399.90,
                    nullable: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Курс успешно обновлён',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Курс с таким кодом уже существует',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Курс с указанным кодом уже существует в системе.'
                ),
            ],
            type: 'object'
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
                    example: 'title: Наименование курса должно содержать не менее 3 символов.'
                ),
                new OA\Property(
                    property: 'violations',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'propertyPath',
                                type: 'string',
                                example: 'title'
                            ),
                            new OA\Property(
                                property: 'title',
                                type: 'string',
                                example: 'Наименование курса должно содержать не менее 3 символов.'
                            ),
                            new OA\Property(
                                property: 'template',
                                type: 'string',
                                example: 'Наименование курса должно содержать не менее 3 символов.'
                            ),
                            new OA\Property(
                                property: 'parameters',
                                type: 'object',
                                example: [
                                    '{{ max }}' => '255',
                                    '{{ value }}' => '"Ос"',
                                    '{{ limit }}' => '3',
                                    '{{ min }}' => '3',
                                    '{{ value_length }}' => '2'
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
    public function editCourse(#[MapRequestPayload] CourseRequestDto $dto, string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return $this->json([
                'message' => 'Курс не найден.',
            ], Response::HTTP_NOT_FOUND);
        }

        $courseWithSameCode = $this->courseRepository->findOneBy(['code' => $dto->code]);
        if ($courseWithSameCode !== null && $courseWithSameCode->getId() !== $course->getId()) {
            return $this->json([
                'message' => 'Курс с указанным кодом уже существует в системе.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $course->setCode($dto->code);
        $course->setTitle($dto->title);
        $course->setType(CourseTypeEnum::fromCode($dto->type));
        if (CourseTypeEnum::fromCode($dto->type) !== CourseTypeEnum::FREE) {
            $course->setPrice($dto->price);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
        ], Response::HTTP_OK);
    }

    #[Route('/{code}/pay', name: 'payment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Courses')]
    #[OA\Post(
        summary: 'Оплата курса',
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Символьный код курса',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            example: 'symfony-basics'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Курс успешно оплачен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'course_type',
                    type: 'string',
                    example: 'rent',
                    enum: ['rent', 'buy']
                ),
                new OA\Property(
                    property: 'expires_at',
                    type: 'string',
                    format: 'date-time',
                    example: '2026-05-08T13:46:07+00:00',
                    nullable: true
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Пользователь не авторизован',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Требуется авторизация.'
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Курс не найден',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Курс не найден.'
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_ACCEPTABLE,
        description: 'Недостаточно средств',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'На вашем счету недостаточно средств.'
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Ошибка оплаты',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Бесплатный курс не требует оплаты.'
                ),
            ],
            type: 'object'
        )
    )]
    public function payCourse(string $code): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Требуется авторизация.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return $this->json([
                'message' => 'Курс не найден.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $this->paymentService->paymentCourse($user, $course);
        } catch (\DomainException $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_ACCEPTABLE);
        } catch (\LogicException $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($data, Response::HTTP_OK);
    }

    private function normalizeCourseData(Course $course): array
    {
        $type = $course->getType();

        $item = [
            'code' => $course->getCode(),
            'title' => $course->getTitle(),
            'type' => $type?->code(),
        ];

        if ($type !== CourseTypeEnum::FREE) {
            $item['price'] = (string) $course->getPrice();
        }

        return $item;
    }
}
