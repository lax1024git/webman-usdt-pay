<?php

declare(strict_types=1);

namespace tests;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected Client $client;
    protected string $baseUrl;
    protected ?string $token = null;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('API_BASE_URL') ?: 'http://127.0.0.1:8787';
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'http_errors' => false,
            'timeout' => 10,
        ]);
        $this->loginAsAdmin();
    }

    protected function loginAsAdmin(): void
    {
        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ], false);
        $this->assertEquals(200, $response['code'], 'Login failed: ' . ($response['msg'] ?? ''));
        $this->token = $response['data']['token'];
    }

    protected function get(string $uri, array $query = []): array
    {
        $options = ['query' => $query];
        if ($this->token) {
            $options['headers'] = ['Authorization' => 'Bearer ' . $this->token];
        }
        $response = $this->client->get($uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function post(string $uri, array $data = [], bool $auth = true): array
    {
        $options = ['json' => $data];
        if ($auth && $this->token) {
            $options['headers'] = ['Authorization' => 'Bearer ' . $this->token];
        }
        $response = $this->client->post($uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function put(string $uri, array $data = []): array
    {
        $options = [
            'json' => $data,
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ];
        $response = $this->client->put($uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function delete(string $uri): array
    {
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->token]];
        $response = $this->client->delete($uri, $options);
        return json_decode($response->getBody()->getContents(), true);
    }
}
