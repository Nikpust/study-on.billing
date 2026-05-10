<?php

namespace App\Dto\Api\V1;

use App\Enum\CourseTypeEnum;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CourseRequestDto
{
    #[Assert\NotBlank(message: 'Тип курса не должен быть пустым.')]
    #[Assert\Choice(
        callback: [CourseTypeEnum::class, 'codes'],
        message: 'Тип курса должен быть одним из: {{ choices }}.'
    )]
    public ?string $type = null;

    #[Assert\NotBlank(message: 'Наименование курса не должно быть пустым.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Наименование курса должно содержать не менее {{ limit }} символов.',
        maxMessage: 'Наименование курса должно содержать не более {{ limit }} символов.',
    )]
    #[OA\Property(example: 'Основы Symfony')]
    public ?string $title = null;

    #[Assert\NotBlank(message: 'Код курса не должен быть пустым.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Код курса должен содержать не менее {{ limit }} символов.',
        maxMessage: 'Код курса должен содержать не более {{ limit }} символов.',
    )]
    #[OA\Property(example: 'symfony-basics')]
    public ?string $code = null;

    #[OA\Property(example: 399.90)]
    public ?float $price = null;

    #[Assert\Callback]
    public function validatePrice(ExecutionContextInterface $context): void
    {
        if ($this->price !== null && $this->type === CourseTypeEnum::FREE->code()) {
            $context
                ->buildViolation('Бесплатный курс не должен иметь цену.')
                ->atPath('price')
                ->addViolation();
        }

        if (
            ($this->price === null || $this->price <= 0)
            && in_array($this->type, [CourseTypeEnum::RENT->code(), CourseTypeEnum::BUY->code()], true)
        ) {
            $context
                ->buildViolation('Платный курс должен иметь положительную цену.')
                ->atPath('price')
                ->addViolation();
        }
    }
}
