<?php

namespace App\Tests\Controller\Api\V1;

use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterReturnsOk(): void
    {
        $client = $this->registerUser();
        self::assertResponseStatusCodeSame(201);
    }

    public function testRegisterReturnsValidJson(): void
    {
        $client = $this->registerUser();
        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('roles', $data);

        self::assertIsString($data['token']);
        self::assertIsArray($data['roles']);

        self::assertNotSame('', $data['token']);
        self::assertContains('ROLE_USER', $data['roles']);
    }

    public function testRegisterCreatesUserInDatabase(): void
    {
        $client = $this->registerUser();
        self::assertResponseStatusCodeSame(201);

        $container = $client->getContainer();
        $user = $container->get(UserRepository::class)->findOneByEmail('user@example.com');
        self::assertNotNull($user);
    }

    public function testRegisterHashedPasswordInDatabase(): void
    {
        $client = $this->registerUser();
        self::assertResponseStatusCodeSame(201);

        $container = $client->getContainer();
        $user = $container->get(UserRepository::class)->findOneByEmail('user@example.com');
        self::assertNotNull($user);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, 'password123'));
    }

    #[DataProvider('invalidDataProvider')]
    public function testRegisterReturns422ForInvalidPayload($dataJson, $errorMessage): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', $dataJson);
        self::assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Validation Failed', $data['title']);
        self::assertSame(422, $data['status']);
        self::assertArrayHasKey('violations', $data);
        self::assertNotEmpty($data['violations']);

        self::assertSame($errorMessage, $data['violations'][0]['title']);
    }

    public function testRegisterReturns400ForMalformedJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{"email":"user@example.com",'
        );

        self::assertResponseStatusCodeSame(400);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'email invalid' => [
                [
                    'email' => 'invalid-email.ru',
                    'password' => 'password123',
                ],
                'Неверный email.',
            ],
            'email blank' => [
                [
                    'email' => '',
                    'password' => 'password123',
                ],
                'Email не должен быть пустым.',
            ],
            'email not unique' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => 'password123',
                ],
                'Указанный email уже зарегистрирован.',
            ],
            'password blank' => [
                [
                    'email' => 'new-user@example.com',
                    'password' => '',
                ],
                'Пароль не должен быть пустым.',
            ],
            'password short' => [
                [
                    'email' => 'new-user@example.com',
                    'password' => 'pass',
                ],
                'Пароль должен быть не короче 6 символов.',
            ],
        ];
    }

    private function registerUser(array $data = [
        'email' => 'user@example.com',
        'password' => 'password123'
    ]): KernelBrowser
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/register', $data);

        return $client;
    }
}
