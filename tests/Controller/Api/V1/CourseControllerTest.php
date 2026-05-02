<?php

namespace App\Tests\Controller\Api\V1;

use App\Entity\Course;
use App\Enum\CourseTypeEnum;
use App\Repository\CourseRepository;
use App\Tests\Traits\V1\AuthenticationTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testGetCoursesReturnsCourseList(): void
    {
        $client = static::createClient();
        $client->jsonRequest('GET', '/api/v1/courses');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        foreach ($data as $course) {
            self::assertArrayHasKey('code', $course);
            self::assertArrayHasKey('type', $course);
        }
    }

    public function testGetCoursesReturnsPricesForPaidCourses(): void
    {
        $client = static::createClient();

        $courseRepository = static::getContainer()->get(CourseRepository::class);

        $buyCourses = $courseRepository->findBy(['type' => CourseTypeEnum::BUY]);
        $rentCourses = $courseRepository->findBy(['type' => CourseTypeEnum::RENT]);

        self::assertNotEmpty($buyCourses, 'В БД не найдены курсы типа buy');
        self::assertNotEmpty($rentCourses, 'В БД не найдены курсы типа rent');

        $paidCourses = [...$buyCourses, ...$rentCourses];

        $client->jsonRequest('GET', '/api/v1/courses');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        $responseByCode = [];

        foreach ($data as $course) {
            self::assertArrayHasKey('code', $course);

            $responseByCode[$course['code']] = $course;
        }

        foreach ($paidCourses as $paidCourse) {
            $code = $paidCourse->getCode();

            self::assertArrayHasKey($code, $responseByCode);
            self::assertArrayHasKey('price', $responseByCode[$code]);
            self::assertIsNumeric($responseByCode[$code]['price']);
            self::assertGreaterThan(0, $responseByCode[$code]['price']);
        }
    }

    public function testGetCourseReturnsCourseByCode(): void
    {
        $client = static::createClient();

        $courseRepository = static::getContainer()->get(CourseRepository::class);
        $course = $courseRepository->findOneBy([]);

        $client->jsonRequest('GET', '/api/v1/courses/' . $course->getCode());

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseIsSuccessful();
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('type', $data);

        self::assertSame($course->getCode(), $data['code']);
        self::assertSame($course->getType()->code(), $data['type']);
    }

    public function testGetCourseReturns404ForMissingCourse(): void
    {
        $client = static::createClient();
        $client->jsonRequest('GET', '/api/v1/courses/missing-course');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Курс не найден.', $data['message']);
    }

    public function testGetCoursesDoesNotReturnPricesForFreeCourses(): void
    {
        $client = static::createClient();

        $courseRepository = static::getContainer()->get(CourseRepository::class);
        $freeCourses = $courseRepository->findBy(['type' => CourseTypeEnum::FREE]);

        self::assertNotEmpty($freeCourses, 'В БД не найдены курсы типа free');

        $client->jsonRequest('GET', '/api/v1/courses');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $responseByCode = [];

        foreach ($data as $course) {
            $responseByCode[$course['code']] = $course;
        }

        foreach ($freeCourses as $freeCourse) {
            $code = $freeCourse->getCode();

            self::assertArrayHasKey($code, $responseByCode);
            self::assertArrayNotHasKey('price', $responseByCode[$code]);
        }
    }

    public function testPayCourseReturns401ForUnauthorizedUser(): void
    {
        $client = static::createClient();

        $course = $this->getCourseByType(CourseTypeEnum::BUY);

        $client->jsonRequest('POST', '/api/v1/courses/' . $course->getCode() . '/pay');
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseStatusCodeSame(401);
        self::assertArrayHasKey('message', $data);
        self::assertSame('JWT Token not found', $data['message']);
    }

    public function testPayCourseReturns400ForFreeCourse(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $course = $this->getCourseByType(CourseTypeEnum::FREE);

        $data = $this->payCourse($client, $course->getCode(), $apiToken);

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('message', $data);
        self::assertSame('Бесплатный курс не требует оплаты.', $data['message']);
    }

    public function testPayCourseReturns404ForMissingCourse(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $data = $this->payCourse($client, 'missing-course', $apiToken);

        self::assertResponseStatusCodeSame(404);
        self::assertArrayHasKey('message', $data);
        self::assertSame('Курс не найден.', $data['message']);
    }

    public function testPayCourseReturns406WhenUserHasInsufficientFunds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $apiToken = $this->loginAsUser($client);

        $courseCode = 'super-expensive-course';

        try {
            $course = new Course();
            $course->setCode($courseCode);
            $course->setType(CourseTypeEnum::BUY);
            $course->setPrice(999999.9);

            $em->persist($course);
            $em->flush();

            $data = $this->payCourse($client, 'super-expensive-course', $apiToken);

            self::assertResponseStatusCodeSame(406);
            self::assertArrayHasKey('message', $data);
            self::assertSame('На вашем счету недостаточно средств.', $data['message']);
        } finally {
            $course = $em->getRepository(Course::class)->findOneBy([
                'code' => $courseCode,
            ]);

            if ($course !== null) {
                $em->remove($course);
                $em->flush();
            }
        }
    }

    public function testPayCourseSuccessfullyBuysCourse(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $course = $this->getCourseByType(CourseTypeEnum::BUY);

        $data = $this->payCourse($client, $course->getCode(), $apiToken);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray($data);

        self::assertArrayHasKey('success', $data);
        self::assertTrue($data['success']);

        self::assertArrayHasKey('course_type', $data);
        self::assertSame($course->getType()->code(), $data['course_type']);

        self::assertArrayNotHasKey('expires_at', $data);
    }

    public function testPayCourseSuccessfullyRentsCourseWithExpiresAt(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $course = $this->getCourseByType(CourseTypeEnum::RENT);

        $data = $this->payCourse($client, $course->getCode(), $apiToken);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray($data);

        self::assertArrayHasKey('success', $data);
        self::assertTrue($data['success']);

        self::assertArrayHasKey('course_type', $data);
        self::assertSame($course->getType()->code(), $data['course_type']);

        self::assertArrayHasKey('expires_at', $data);
        self::assertNotSame('', $data['expires_at']);
    }

    private function getCourseByType(CourseTypeEnum $type): Course
    {
        $courseRepository = static::getContainer()->get(CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => $type]);

        self::assertNotNull($course, sprintf('В БД не найден курс типа %s', $type->code()));

        return $course;
    }

    private function payCourse(KernelBrowser $client, string $courseCode, string $apiToken): array
    {
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/' . $courseCode . '/pay',
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $apiToken]
        );

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
