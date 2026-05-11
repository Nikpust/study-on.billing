<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
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
        $firstUser = $this->getReference(UserFixtures::USER_REFERENCE, User::class);
        $secondUser = $this->getReference(UserFixtures::SECOND_USER_REFERENCE, User::class);

        $symfonyCourse = $this->getReference(CourseFixtures::COURSE_SYMFONY_REFERENCE, Course::class);
        $phpCourse = $this->getReference(CourseFixtures::COURSE_PHP_REFERENCE, Course::class);
        $apiCourse = $this->getReference(CourseFixtures::COURSE_API_REFERENCE, Course::class);
        $databaseCourse = $this->getReference(CourseFixtures::COURSE_DATABASE_REFERENCE, Course::class);
        $testingCourse = $this->getReference(CourseFixtures::COURSE_TESTING_REFERENCE, Course::class);
        $dockerCourse = $this->getReference(CourseFixtures::COURSE_DOCKER_REFERENCE, Course::class);

        $this->paymentService->paymentCourse($firstUser, $symfonyCourse);
        $this->paymentService->paymentCourse($firstUser, $phpCourse);

        $this->createPaymentTransaction(
            $manager,
            $firstUser,
            $apiCourse,
            new DateTimeImmutable('-6 days'),
            new DateTimeImmutable('tomorrow 12:00:00')
        );

        $this->createPaymentTransaction(
            $manager,
            $firstUser,
            $databaseCourse,
            new DateTimeImmutable('-2 weeks'),
            new DateTimeImmutable('-1 weeks')
        );

        $this->paymentService->paymentCourse($secondUser, $dockerCourse);

        $this->createPaymentTransaction(
            $manager,
            $secondUser,
            $testingCourse,
            new DateTimeImmutable('-6 days'),
            new DateTimeImmutable('tomorrow 10:00:00')
        );

        $this->createPaymentTransaction(
            $manager,
            $secondUser,
            $apiCourse,
            new DateTimeImmutable('-6 days'),
            new DateTimeImmutable('tomorrow 14:00:00')
        );

        $this->createPaymentTransaction(
            $manager,
            $secondUser,
            $databaseCourse,
            new DateTimeImmutable('-6 days'),
            new DateTimeImmutable('tomorrow 18:00:00')
        );

        $this->createPaymentTransaction(
            $manager,
            $secondUser,
            $apiCourse,
            new DateTimeImmutable('-2 weeks'),
            new DateTimeImmutable('-1 weeks')
        );

        $manager->flush();
    }

    private function createPaymentTransaction(
        ObjectManager $manager,
        User $user,
        Course $course,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $expiresAt = null
    ): void {
        $transaction = new Transaction();
        $transaction->setUserBilling($user);
        $transaction->setCourse($course);
        $transaction->setType(TransactionTypeEnum::PAYMENT);
        $transaction->setAmount((float) $course->getPrice());
        $transaction->setCreatedAt($createdAt);

        if ($course->getType() === CourseTypeEnum::RENT) {
            $transaction->setExpiresAt($expiresAt);
        }

        $manager->persist($transaction);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CourseFixtures::class,
        ];
    }
}
