<?php

namespace App\Tests\Controller\Api\V1;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
use App\Enum\TransactionTypeEnum;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use App\Tests\Traits\V1\AuthenticationTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TransactionControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testGetTransactionsReturns401ForUnauthorizedUser(): void
    {
        $client = static::createClient();

        $client->jsonRequest('GET', '/api/v1/transactions');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseStatusCodeSame(401);
        self::assertArrayHasKey('message', $data);
        self::assertSame('JWT Token not found', $data['message']);
    }

    public function testGetTransactionsReturnsTransactionList(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $data = $this->getTransactions($client, $apiToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        foreach ($data as $transaction) {
            self::assertArrayHasKey('id', $transaction);
            self::assertArrayHasKey('created_at', $transaction);
            self::assertArrayHasKey('type', $transaction);
            self::assertArrayHasKey('amount', $transaction);

            self::assertIsInt($transaction['id']);
            self::assertContains($transaction['type'], [
                TransactionTypeEnum::PAYMENT->code(),
                TransactionTypeEnum::DEPOSIT->code()
            ]);
            self::assertIsNumeric($transaction['amount']);

            self::assertNotSame('', $transaction['created_at']);

            if ($transaction['type'] === TransactionTypeEnum::PAYMENT->code()) {
                self::assertArrayHasKey('course_code', $transaction);
                self::assertNotSame('', $transaction['course_code']);
            }
            if ($transaction['type'] === TransactionTypeEnum::DEPOSIT->code()) {
                self::assertArrayNotHasKey('course_code', $transaction);
            }
        }
    }

    public function testGetTransactionsFiltersByType(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $data = $this->getTransactions($client, $apiToken, '?filter[type][]=payment');

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);

        foreach ($data as $transaction) {
            self::assertSame(TransactionTypeEnum::PAYMENT->code(), $transaction['type']);
        }
    }

    public function testGetTransactionsFiltersByCourseCode(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $courseRepository = self::getContainer()->get(CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => CourseTypeEnum::BUY]);

        $data = $this->getTransactions($client, $apiToken, '?filter[course_code]=' . $course->getCode());

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);

        foreach ($data as $transaction) {
            self::assertArrayHasKey('course_code', $transaction);
            self::assertSame($course->getCode(), $transaction['course_code']);
        }
    }

    public function testGetTransactionsFiltersExpiredRentTransactions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        $courseRepository = static::getContainer()->get(CourseRepository::class);

        $user = $userRepository->findOneBy(['email' => 'test-user@mail.ru']);
        $course = $courseRepository->findOneBy(['type' => CourseTypeEnum::RENT]);

        self::assertNotNull($user, 'В БД не найден пользователь test-user@mail.ru');
        self::assertNotNull($course, sprintf('В БД не найден курс типа %s', CourseTypeEnum::RENT->code()));

        $expiredTransaction = null;
        $activeTransaction = null;

        try {
            $expiredTransaction = new Transaction();
            $expiredTransaction->setUserBilling($user);
            $expiredTransaction->setCourse($course);
            $expiredTransaction->setType(TransactionTypeEnum::PAYMENT);
            $expiredTransaction->setAmount($course->getPrice());
            $expiredTransaction->setCreatedAt(new \DateTimeImmutable('-2 weeks'));
            $expiredTransaction->setExpiresAt(new \DateTimeImmutable('-1 week'));

            $activeTransaction = new Transaction();
            $activeTransaction->setUserBilling($user);
            $activeTransaction->setCourse($course);
            $activeTransaction->setType(TransactionTypeEnum::PAYMENT);
            $activeTransaction->setAmount($course->getPrice());
            $activeTransaction->setCreatedAt(new \DateTimeImmutable());
            $activeTransaction->setExpiresAt(new \DateTimeImmutable('+1 week'));

            $em->persist($expiredTransaction);
            $em->persist($activeTransaction);
            $em->flush();

            $apiToken = $this->loginAsUser($client);

            $data = $this->getTransactions($client, $apiToken, '?filter[skip_expired]=true');

            self::assertResponseIsSuccessful();
            self::assertIsArray($data);

            $ids = array_column($data, 'id');

            self::assertNotContains($expiredTransaction->getId(), $ids);
            self::assertContains($activeTransaction->getId(), $ids);
        } finally {
            if ($expiredTransaction !== null) {
                $expiredTransaction = $em->getRepository(Transaction::class)->find($expiredTransaction->getId());

                if ($expiredTransaction !== null) {
                    $em->remove($expiredTransaction);
                }
            }

            if ($activeTransaction !== null) {
                $activeTransaction = $em->getRepository(Transaction::class)->find($activeTransaction->getId());

                if ($activeTransaction !== null) {
                    $em->remove($activeTransaction);
                }
            }

            $em->flush();
        }
    }

    public function testGetTransactionsReturnsOnlyCurrentUserTransactions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $paymentService = static::getContainer()->get(PaymentService::class);

        $currentUser = $userRepository->findOneBy(['email' => 'test-user@mail.ru']);
        self::assertNotNull($currentUser, 'В БД не найден пользователь test-user@mail.ru');

        $anotherUser = null;
        $anotherUserTransaction = null;
        $currentUserTransaction = null;

        try {
            $anotherUser = new User();
            $anotherUser->setEmail('another-user@mail.ru');
            $anotherUser->setPassword($hasher->hashPassword($anotherUser, 'password'));

            $em->persist($anotherUser);
            $em->flush();

            $paymentService->depositBalance($currentUser, 9999999.9);
            $paymentService->depositBalance($anotherUser, 8888888.8);

            $currentUserTransaction = $em->getRepository(Transaction::class)->findOneBy([
                'userBilling' => $currentUser,
                'type' => TransactionTypeEnum::DEPOSIT,
                'amount' => 9999999.9,
            ]);

            $anotherUserTransaction = $em->getRepository(Transaction::class)->findOneBy([
                'userBilling' => $anotherUser,
                'type' => TransactionTypeEnum::DEPOSIT,
                'amount' => 8888888.8,
            ]);

            self::assertNotNull($currentUserTransaction);
            self::assertNotNull($anotherUserTransaction);

            $apiToken = $this->loginAsUser($client);

            $data = $this->getTransactions($client, $apiToken);

            self::assertResponseIsSuccessful();

            $ids = array_column($data, 'id');

            self::assertContains($currentUserTransaction->getId(), $ids);
            self::assertNotContains($anotherUserTransaction->getId(), $ids);
        } finally {
            if ($currentUserTransaction !== null) {
                $currentUserTransaction = $em
                    ->getRepository(Transaction::class)
                    ->find($currentUserTransaction->getId());

                if ($currentUserTransaction !== null) {
                    $em->remove($currentUserTransaction);
                }
            }

            if ($anotherUserTransaction !== null) {
                $anotherUserTransaction = $em
                    ->getRepository(Transaction::class)
                    ->find($anotherUserTransaction->getId());

                if ($anotherUserTransaction !== null) {
                    $em->remove($anotherUserTransaction);
                }
            }

            if ($anotherUser !== null) {
                $anotherUser = $em
                    ->getRepository(User::class)
                    ->find($anotherUser->getId());

                if ($anotherUser !== null) {
                    $em->remove($anotherUser);
                }
            }

            $em->flush();
        }
    }

    private function getTransactions(KernelBrowser $client, string $apiToken, string $query = ''): array
    {
        $client->jsonRequest(
            'GET',
            '/api/v1/transactions' . $query,
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $apiToken]
        );

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
