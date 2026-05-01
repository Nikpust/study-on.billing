<?php

namespace App\Controller\Api\V1;

use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseTypeEnum;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/courses', name: 'api_v1_courses_', format: 'json')]
final class CourseController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly PaymentService $paymentService,
    ) {
    }

    #[Route(methods: ['GET'])]
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

    #[Route('/{code}', name: 'show', methods: ['GET'])]
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

    #[Route('/{code}/pay', name: 'payment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
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
            'type' => $type?->code(),
        ];

        if ($type !== CourseTypeEnum::FREE) {
            $item['price'] = $course->getPrice();
        }

        return $item;
    }
}
