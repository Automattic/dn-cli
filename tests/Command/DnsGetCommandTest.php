<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Dns\Get as DnsGetResponse;
use DnCli\Command\DnsGetCommand;

class DnsGetCommandTest extends CommandTestCase
{
    public function test_dns_get_success(): void
    {
        $response = new DnsGetResponse($this->successData([
            'data' => [
                'dns_records' => [
                    'domain' => 'example.com',
                    'record_sets' => [
                        [
                            'name' => '@',
                            'type' => 'A',
                            'ttl' => 3600,
                            'data' => ['1.2.3.4'],
                        ],
                        [
                            'name' => 'www',
                            'type' => 'CNAME',
                            'ttl' => 3600,
                            'data' => ['example.com'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('A', $output);
        $this->assertStringContainsString('1.2.3.4', $output);
        $this->assertStringContainsString('CNAME', $output);
        $this->assertStringContainsString('www', $output);
    }

    public function test_dns_get_multiple_values(): void
    {
        $response = new DnsGetResponse($this->successData([
            'data' => [
                'dns_records' => [
                    'domain' => 'example.com',
                    'record_sets' => [
                        [
                            'name' => '@',
                            'type' => 'A',
                            'ttl' => 300,
                            'data' => ['1.2.3.4', '5.6.7.8'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('1.2.3.4', $output);
        $this->assertStringContainsString('5.6.7.8', $output);
    }

    public function test_dns_get_empty_records(): void
    {
        $response = new DnsGetResponse($this->successData([
            'data' => [
                'dns_records' => [
                    'domain' => 'example.com',
                    'record_sets' => [],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No DNS records found', $tester->getDisplay());
    }

    public function test_dns_get_api_error(): void
    {
        $response = new DnsGetResponse($this->errorData('Not found'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_dns_get_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Error'));

        $tester = $this->createTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new DnsGetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
