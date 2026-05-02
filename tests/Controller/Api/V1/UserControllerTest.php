<?php

namespace App\Tests\Controller\Api\V1;

use App\Tests\Traits\V1\AuthenticationTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testGetCurrentUserReturnsUser(): void
    {
        $client = static::createClient();
        $apiToken = $this->loginAsUser($client);

        $client->request('GET', '/api/v1/users/current', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $apiToken]);
        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        self::assertArrayHasKey('username', $data);
        self::assertArrayHasKey('roles', $data);
        self::assertArrayHasKey('balance', $data);

        self::assertIsString($data['username']);
        self::assertIsArray($data['roles']);
        self::assertIsNumeric($data['balance']);

        self::assertSame('test-user@mail.ru', $data['username']);
        self::assertContains('ROLE_USER', $data['roles']);
        self::assertIsNumeric((float) $data['balance']);
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
}
