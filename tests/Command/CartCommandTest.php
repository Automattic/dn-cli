<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use DnCli\Command\CartCommand;

class CartCommandTest extends CommandTestCase
{
    public function test_cart_with_products(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->with('rest/v1.1/me/shopping-cart/no-site')
            ->willReturn([
                'products' => [
                    [
                        'meta' => 'example.com',
                        'product_slug' => 'dotcom_domain',
                        'product_name' => 'Domain Registration',
                        'cost_display' => '$12.00',
                    ],
                ],
            ]);

        $tester = $this->createUserModeTester(new CartCommand());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('Domain Registration', $output);
        $this->assertStringContainsString('$12.00', $output);
    }

    public function test_cart_empty(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->willReturn(['products' => []]);

        $tester = $this->createUserModeTester(new CartCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('cart is empty', $tester->getDisplay());
    }

    public function test_cart_partner_mode_errors(): void
    {
        $tester = $this->createTester(new CartCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('only available in user mode', $tester->getDisplay());
    }

    public function test_cart_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Unauthorized'));

        $tester = $this->createUserModeTester(new CartCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $tester->getDisplay());
    }
}
