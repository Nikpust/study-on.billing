<?php

namespace App\Tests\Traits\V1;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait AuthenticationTestTrait
{
    private function login(KernelBrowser $client, string $username, string $password): string
    {
        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => $username,
            'password' => $password,
        ]);
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);

        return $data['token'];
    }

    protected function loginAsUser(KernelBrowser $client): string
    {
        return $this->login($client, 'test-user@mail.ru', 'user-password');
    }

    protected function loginAsAdmin(KernelBrowser $client): string
    {
        return $this->login($client, 'test-admin@mail.ru', 'password');
    }
}
