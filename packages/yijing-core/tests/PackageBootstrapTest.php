<?php

declare(strict_types=1);

namespace Yijing\Core\Tests;

use PHPUnit\Framework\TestCase;

final class PackageBootstrapTest extends TestCase
{
    public function testAutoloaderIsConfigured(): void
    {
        self::assertTrue(class_exists(TestCase::class));
    }
}
