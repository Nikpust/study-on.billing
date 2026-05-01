<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionTypeEnum;
use App\Service\PaymentService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = $this->getReference(UserFixtures::USER_REFERENCE, User::class);

        $courses = [
            $this->getReference(CourseFixtures::COURSE_SYMFONY_REFERENCE, Course::class),
            $this->getReference(CourseFixtures::COURSE_PHP_REFERENCE, Course::class),
            $this->getReference(CourseFixtures::COURSE_API_REFERENCE, Course::class),
        ];

        foreach ($courses as $course) {
            $this->paymentService->paymentCourse($user, $course);
        }

        $expiredRentCourse = $this->getReference(CourseFixtures::COURSE_DATABASE_REFERENCE, Course::class);

        $expiredTransaction = new Transaction();
        $expiredTransaction->setUserBilling($user);
        $expiredTransaction->setCourse($expiredRentCourse);
        $expiredTransaction->setType(TransactionTypeEnum::PAYMENT);
        $expiredTransaction->setAmount($expiredRentCourse->getPrice());
        $expiredTransaction->setCreatedAt(new DateTimeImmutable('-2 weeks'));
        $expiredTransaction->setExpiresAt(new DateTimeImmutable('-1 week'));

        $manager->persist($expiredTransaction);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CourseFixtures::class,
        ];
    }
}
