<?php

declare(strict_types=1);

namespace tests\Api;

use tests\ApiTestCase;

class AuthApiTest extends ApiTestCase
{
    public function testLoginSuccess(): void
    {
        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ], false);
        $this->assertEquals(200, $response['code']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayHasKey('refresh_token', $response['data']);
        $this->assertEquals('admin', $response['data']['user']['username']);
    }

    public function testLoginFail(): void
    {
        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'wrongpassword',
        ], false);
        $this->assertNotEquals(200, $response['code']);
    }

    public function testGetMe(): void
    {
        $response = $this->get('/admin/me');
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('admin', $response['data']['username']);
        $this->assertIsArray($response['data']['permissions']);
    }

    public function testGetMenus(): void
    {
        $response = $this->get('/admin/menus');
        $this->assertEquals(200, $response['code']);
        $this->assertIsArray($response['data']);
        $this->assertNotEmpty($response['data']);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->token = null;
        $client = new \GuzzleHttp\Client(['base_uri' => $this->baseUrl, 'http_errors' => false]);
        $response = $client->get('/admin/admins');
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals(40101, $body['code']);
    }

    public function testRefreshToken(): void
    {
        $login = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ], false);
        $refreshToken = $login['data']['refresh_token'];

        $response = $this->post('/admin/refresh', ['refresh_token' => $refreshToken], false);
        $this->assertEquals(200, $response['code']);
        $this->assertArrayHasKey('token', $response['data']);
    }
}
