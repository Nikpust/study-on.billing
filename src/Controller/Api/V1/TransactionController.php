<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\TransactionRepository;
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

            $item['amount'] = $transaction->getAmount();

            $normalizeTransactions[] = $item;
        }

        return new JsonResponse(
            $normalizeTransactions,
            Response::HTTP_OK
        );
    }
}
