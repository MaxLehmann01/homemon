<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testDashboardAndLiveEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();
        foreach (['/', '/plugs/neu', '/_components/PlugCard'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testPasswordLoginRejectsInvalidCredentialsAndAcceptsConfiguredPassword(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $client->submitForm('Sign in', ['password' => 'incorrect']);
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        $client->submitForm('Sign in', ['password' => 'test-password']);
        self::assertResponseRedirects('/');
        $client->request('GET', '/login');
        self::assertResponseRedirects('/');
    }

    public function testLoginRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', ['password' => 'test-password', '_csrf_token' => 'invalid']);
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }
}
