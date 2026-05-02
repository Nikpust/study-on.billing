<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\TransactionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/transactions', name: 'api_v1_transactions_', format: 'json')]
final class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    #[Route(methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Tag(name: 'Transactions')]
    #[OA\Get(
        summary: 'История транзакций',
    )]
    #[OA\Parameter(
        name: 'filter[type][]',
        description: 'Тип транзакции',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'array',
            items: new OA\Items(
                type: 'string',
                enum: ['payment', 'deposit']
            ),
            example: ['payment', 'deposit']
        )
    )]
    #[OA\Parameter(
        name: 'filter[course_code]',
        description: 'Символьный код курса',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            example: 'symfony-basics'
        )
    )]
    #[OA\Parameter(
        name: 'filter[skip_expired]',
        description: 'Не показывать истёкшие транзакции по арендованным курсам',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'boolean',
            example: true
        )
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'История транзакций',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(
                        property: 'id',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'created_at',
                        type: 'string',
                        format: 'date-time',
                        example: '2026-05-01T13:46:07+00:00'
                    ),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        example: 'payment',
                        enum: ['payment', 'deposit']
                    ),
                    new OA\Property(
                        property: 'course_code',
                        type: 'string',
                        example: 'symfony-basics',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'amount',
                        type: 'string',
                        example: '399.90'
                    ),
                    new OA\Property(
                        property: 'expires_at',
                        type: 'string',
                        example: '2026-05-08T13:46:07+00:00',
                        nullable: true
                    ),
                ],
                type: 'object'
            )
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
    public function getTransactions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'message' => 'Требуется авторизация.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $filters = $request->query->all('filter');
        $transactions = $this->transactionRepository->findByUserWithFilters($user, $filters);

        $normalizeTransactions = [];
        foreach ($transactions as $transaction) {
            $item = [
                'id' => $transaction->getId(),
                'created_at' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'type' => $transaction->getType()?->code(),
            ];

            $transactionCourse = $transaction->getCourse();
            if ($transactionCourse !== null) {
                $item['course_code'] = $transactionCourse->getCode();
            }

            $item['amount'] = (string) $transaction->getAmount();

            $transactionExpiresAt = $transaction->getExpiresAt();
            if ($transactionExpiresAt !== null) {
                $item['expires_at'] = $transactionExpiresAt->format(\DateTimeInterface::ATOM);
            }

            $normalizeTransactions[] = $item;
        }

        return new JsonResponse(
            $normalizeTransactions,
            Response::HTTP_OK
        );
    }
}
