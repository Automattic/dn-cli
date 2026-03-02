<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Dns\Set as DnsSetResponse;
use DnCli\Command\DnsSetCommand;

class DnsSetCommandTest extends CommandTestCase
{
    public function test_dns_set_success(): void
    {
        $response = new DnsSetResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['1.2.3.4'],
            '--ttl' => '3600',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DNS record set', $output);
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('1.2.3.4', $output);
    }

    public function test_dns_set_multiple_values(): void
    {
        $response = new DnsSetResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['1.2.3.4', '5.6.7.8'],
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_dns_set_type_uppercased(): void
    {
        $response = new DnsSetResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'cname',
            '--name' => 'www',
            '--value' => ['example.com'],
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('CNAME', $tester->getDisplay());
    }

    public function test_dns_set_api_error(): void
    {
        $response = new DnsSetResponse($this->errorData('Invalid record'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['bad'],
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid record', $tester->getDisplay());
    }

    public function test_dns_set_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Error'));

        $tester = $this->createTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['1.2.3.4'],
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['1.2.3.4'],
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new DnsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'A',
            '--name' => '@',
            '--value' => ['1.2.3.4'],
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
