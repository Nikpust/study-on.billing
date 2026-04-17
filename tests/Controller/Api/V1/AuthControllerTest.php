<?php

namespace App\Tests\Controller\Api\V1;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testAuthApiReturnsOk(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'test-user@mail.ru',
            'password' => 'user-password',
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testAuthApiReturns401ForInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'invalid@mail.ru',
            'password' => 'user-password',
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthApiReturnsBadRequestForEmptyPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'test-user@mailru',
            'password' => '',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testAuthApiReturnsBadRequestForEmptyEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => '',
            'password' => 'password',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testAuthApiReturnsTokenInResponseBody(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'test-user@mail.ru',
            'password' => 'user-password',
        ]);
        self::assertResponseStatusCodeSame(200);

        self::assertResponseFormatSame('json');

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);
    }

    public function testAuthApiReturnsBadRequestForInvalidJson(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'test-user@mail.ru',
            'user-password',
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
