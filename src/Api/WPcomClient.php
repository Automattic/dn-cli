<?php

declare(strict_types=1);

namespace DnCli\Api;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class WPcomClient
{
    private const BASE_URI = 'https://public-api.wordpress.com/';

    private string $token;
    private ClientInterface $http;

    public function __construct(string $token, ?ClientInterface $http = null)
    {
        $this->token = $token;
        $this->http = $http ?? new Client(['base_uri' => self::BASE_URI]);
    }

    public function get(string $path, array $query = []): array
    {
        $response = $this->http->request('GET', $path, [
            'headers' => $this->headers(),
            'query' => $query,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function post(string $path, array $body = []): array
    {
        $response = $this->http->request('POST', $path, [
            'headers' => $this->headers(),
            'json' => $body,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }
}
