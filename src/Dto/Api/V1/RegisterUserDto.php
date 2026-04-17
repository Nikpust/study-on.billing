<?php

namespace App\Dto\Api\V1;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['email'], message: 'Указанный email уже зарегистрирован.', entityClass: User::class)]
final class RegisterUserDto
{
    #[Assert\NotBlank(message: 'Email не должен быть пустым.')]
    #[Assert\Email(message: 'Неверный email.')]
    #[OA\Property(example: 'user@example.com')]
    public string $email;

    #[Assert\NotBlank(message: 'Пароль не должен быть пустым.')]
    #[Assert\Length(min: 6, minMessage: 'Пароль должен быть не короче {{ limit }} символов.')]
    #[OA\Property(example: 'password123')]
    public string $password;
}
