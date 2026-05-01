<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
use App\Enum\TransactionTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function paymentCourse(User $user, Course $course): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $course): array {
            $typeCourse = $course->getType();
            if ($typeCourse === CourseTypeEnum::FREE) {
                throw new \LogicException('Бесплатный курс не требует оплаты.');
            }

            $priceCourse = $course->getPrice();
            if ($priceCourse === null) {
                throw new \LogicException('В данный момент оплата невозможна. Для курса не указана стоимость.');
            }

            $newUserBalance = $user->getBalance() - $priceCourse;
            if ($newUserBalance < 0) {
                throw new \DomainException('На вашем счету недостаточно средств.');
            }

            $transaction = new Transaction();
            $transaction->setUserBilling($user);
            $transaction->setCourse($course);
            $transaction->setType(TransactionTypeEnum::PAYMENT);
            $transaction->setAmount($priceCourse);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            $response = [
                'success' => true,
                'course_type' => $typeCourse?->code(),
            ];

            if ($typeCourse === CourseTypeEnum::RENT) {
                $expiresAt = (new \DateTimeImmutable())->modify('+1 week');

                $transaction->setExpiresAt($expiresAt);
                $response['expires_at'] = $expiresAt->format(\DateTimeInterface::ATOM);
            }

            $user->setBalance($newUserBalance);

            $this->entityManager->persist($transaction);

            return $response;
        });
    }

    public function depositBalance(User $user, float $deposit): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $deposit): array {
            if ($deposit <= 0) {
                throw new \LogicException('Невозможно пополнить счёт на указанную сумму.');
            }

            $transaction = new Transaction();
            $transaction->setUserBilling($user);
            $transaction->setType(TransactionTypeEnum::DEPOSIT);
            $transaction->setAmount($deposit);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            $user->setBalance($user->getBalance() + $deposit);

            $this->entityManager->persist($transaction);

            return [
                'success' => true,
            ];
        });
    }
}
