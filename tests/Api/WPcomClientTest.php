<?php

declare(strict_types=1);

namespace DnCli\Tests\Api;

use DnCli\Api\WPcomClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WPcomClientTest extends TestCase
{
    private ClientInterface&MockObject $http;
    private WPcomClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = $this->createMock(ClientInterface::class);
        $this->client = new WPcomClient('test-token', $this->http);
    }

    public function test_get_sends_bearer_auth(): void
    {
        $this->http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'rest/v1.3/domains/example.com/is-available',
                $this->callback(function (array $options) {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer test-token';
                })
            )
            ->willReturn(new Response(200, [], json_encode(['available' => true])));

        $result = $this->client->get('rest/v1.3/domains/example.com/is-available');

        $this->assertTrue($result['available']);
    }

    public function test_get_passes_query_params(): void
    {
        $this->http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'rest/v1.1/domains/suggestions',
                $this->callback(function (array $options) {
                    return ($options['query']['query'] ?? '') === 'coffee'
                        && ($options['query']['quantity'] ?? 0) === 5;
                })
            )
            ->willReturn(new Response(200, [], json_encode(['suggestions' => []])));

        $result = $this->client->get('rest/v1.1/domains/suggestions', [
            'query' => 'coffee',
            'quantity' => 5,
        ]);

        $this->assertSame([], $result['suggestions']);
    }

    public function test_post_sends_json_body(): void
    {
        $this->http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'rest/v1.1/me/shopping-cart/no-site',
                $this->callback(function (array $options) {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer test-token'
                        && ($options['json']['product_slug'] ?? '') === 'dotcom_domain';
                })
            )
            ->willReturn(new Response(200, [], json_encode(['cart_key' => '123'])));

        $result = $this->client->post('rest/v1.1/me/shopping-cart/no-site', [
            'product_slug' => 'dotcom_domain',
        ]);

        $this->assertSame('123', $result['cart_key']);
    }

    public function test_post_with_empty_body(): void
    {
        $this->http->expects($this->once())
            ->method('request')
            ->with('POST', 'rest/v1/path', $this->callback(function (array $options) {
                return ($options['json'] ?? null) === [];
            }))
            ->willReturn(new Response(200, [], json_encode(['ok' => true])));

        $result = $this->client->post('rest/v1/path');

        $this->assertTrue($result['ok']);
    }
}
