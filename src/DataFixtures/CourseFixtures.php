<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Enum\CourseTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public const COURSE_WEB_REFERENCE = 'billing-course-web';
    public const COURSE_PHP_REFERENCE = 'billing-course-php';
    public const COURSE_DATABASE_REFERENCE = 'billing-course-database';
    public const COURSE_SYMFONY_REFERENCE = 'billing-course-symfony';
    public const COURSE_API_REFERENCE = 'billing-course-api';
    public const COURSE_DOCKER_REFERENCE = 'billing-course-docker';
    public const COURSE_TESTING_REFERENCE = 'billing-course-testing';
    public const COURSE_FRONTEND_REFERENCE = 'billing-course-frontend';

    public function load(ObjectManager $manager): void
    {
        $coursesData = [
            [
                'reference' => self::COURSE_WEB_REFERENCE,
                'code' => 'web-development-basics',
                'type' => CourseTypeEnum::FREE,
            ],
            [
                'reference' => self::COURSE_PHP_REFERENCE,
                'code' => 'php-backend-development',
                'type' => CourseTypeEnum::BUY,
                'price' => 459.0,
            ],
            [
                'reference' => self::COURSE_DATABASE_REFERENCE,
                'code' => 'database-design-postgresql',
                'type' => CourseTypeEnum::RENT,
                'price' => 99.5,
            ],
            [
                'reference' => self::COURSE_SYMFONY_REFERENCE,
                'code' => 'symfony-basics',
                'type' => CourseTypeEnum::BUY,
                'price' => 249.0,
            ],
            [
                'reference' => self::COURSE_API_REFERENCE,
                'code' => 'rest-api-development',
                'type' => CourseTypeEnum::RENT,
                'price' => 59.5,
            ],
            [
                'reference' => self::COURSE_DOCKER_REFERENCE,
                'code' => 'docker-for-developers',
                'type' => CourseTypeEnum::BUY,
                'price' => 200.0,
            ],
            [
                'reference' => self::COURSE_TESTING_REFERENCE,
                'code' => 'automated-testing-php',
                'type' => CourseTypeEnum::RENT,
                'price' => 65.5,
            ],
            [
                'reference' => self::COURSE_FRONTEND_REFERENCE,
                'code' => 'frontend-with-bootstrap',
                'type' => CourseTypeEnum::FREE,
            ],
        ];

        foreach ($coursesData as $courseData) {
            $course = new Course();
            $course->setCode($courseData['code']);
            $course->setType($courseData['type']);
            if ($courseData['type'] !== CourseTypeEnum::FREE) {
                $course->setPrice($courseData['price']);
            }

            $manager->persist($course);
            $this->addReference($courseData['reference'], $course);
        }

        $manager->flush();
    }
}
