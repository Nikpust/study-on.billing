<?php

namespace App\Tests\Controller\Api\V1;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    public function testGetCurrentUserReturnsUser(): void
    {
        $username = 'test-user@mail.ru';

        $result = $this->authenticateUser([
            'username' => $username,
            'password' => 'user-password',
        ]);

        $client = $result['client'];
        $token = $result['token'];

        $client->request('GET', '/api/v1/users/current', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        self::assertArrayHasKey('username', $data);
        self::assertArrayHasKey('roles', $data);
        self::assertArrayHasKey('balance', $data);

        self::assertIsString($data['username']);
        self::assertIsArray($data['roles']);
        self::assertIsNumeric($data['balance']);

        self::assertSame($username, $data['username']);
        self::assertContains('ROLE_USER', $data['roles']);
        self::assertSame(0.0, (float) $data['balance']);
    }

    public function testGetCurrentUserReturns401ForUnauthorizedUser(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/users/current');
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetCurrentUserReturns401ForInvalidToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/users/current', [], [], ['HTTP_AUTHORIZATION' => 'Bearer invalid']);
        self::assertResponseStatusCodeSame(401);
    }

    private function authenticateUser(array $data): array
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', $data);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);

        return [
            'client' => $client,
            'token' => $data['token'],
        ];
    }
}
