<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Entity\Suggestion;
use Automattic\Domain_Services_Client\Entity\Suggestions;
use Automattic\Domain_Services_Client\Response\Domain\Suggestions as SuggestionsResponse;
use DnCli\Command\SuggestCommand;

class SuggestCommandTest extends CommandTestCase
{
    public function test_basic_suggestions(): void
    {
        $response = new SuggestionsResponse($this->successData([
            'data' => [
                'suggestions' => [
                    [
                        'name' => 'coffee.com',
                        'reseller_register_fee' => 1200,
                        'reseller_renewal_fee' => 1200,
                        'is_premium' => false,
                        'is_available' => true,
                        'zone_is_active' => true,
                    ],
                    [
                        'name' => 'coffee.net',
                        'reseller_register_fee' => 900,
                        'reseller_renewal_fee' => 900,
                        'is_premium' => false,
                        'is_available' => false,
                        'zone_is_active' => true,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new SuggestCommand());
        $tester->execute(['query' => 'coffee']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('coffee.com', $output);
        $this->assertStringContainsString('coffee.net', $output);
        $this->assertStringContainsString('$12.00', $output);
        $this->assertStringContainsString('$9.00', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_premium_domain(): void
    {
        $response = new SuggestionsResponse($this->successData([
            'data' => [
                'suggestions' => [
                    [
                        'name' => 'ai.com',
                        'reseller_register_fee' => 50000,
                        'reseller_renewal_fee' => 50000,
                        'is_premium' => true,
                        'is_available' => true,
                        'zone_is_active' => true,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new SuggestCommand());
        $tester->execute(['query' => 'ai']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Yes', $output); // premium
        $this->assertStringContainsString('$500.00', $output);
    }

    public function test_api_error(): void
    {
        $response = new SuggestionsResponse($this->errorData('Invalid query'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new SuggestCommand());
        $tester->execute(['query' => 'test']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid query', $tester->getDisplay());
    }

    public function test_exception_handling(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Timeout'));

        $tester = $this->createTester(new SuggestCommand());
        $tester->execute(['query' => 'test']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Timeout', $tester->getDisplay());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new SuggestCommand());
        $tester->execute(['query' => 'test']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Not configured', $tester->getDisplay());
    }

    public function test_user_mode_suggestions(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->with('rest/v1.1/domains/suggestions', [
                'query' => 'coffee',
                'quantity' => 10,
            ])
            ->willReturn([
                ['domain_name' => 'coffee.com', 'cost' => '$12.00', 'is_premium' => false, 'relevance' => 1.0],
                ['domain_name' => 'coffee.net', 'cost' => '$9.00', 'is_premium' => false, 'relevance' => 0.9],
            ]);

        $tester = $this->createUserModeTester(new SuggestCommand());
        $tester->execute(['query' => 'coffee']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('coffee.com', $output);
        $this->assertStringContainsString('coffee.net', $output);
        $this->assertStringContainsString('$12.00', $output);
    }

    public function test_user_mode_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Service unavailable'));

        $tester = $this->createUserModeTester(new SuggestCommand());
        $tester->execute(['query' => 'test']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Service unavailable', $tester->getDisplay());
    }
}
