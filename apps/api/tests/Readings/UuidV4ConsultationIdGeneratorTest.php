<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Readings\UuidV4ConsultationIdGenerator;
use PHPUnit\Framework\TestCase;

final class UuidV4ConsultationIdGeneratorTest extends TestCase
{
    public function testGenerateProducesARfc4122CompliantUuidV4(): void
    {
        $id = (new UuidV4ConsultationIdGenerator())->generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id,
        );
    }

    public function testGenerateProducesUniqueValues(): void
    {
        $generator = new UuidV4ConsultationIdGenerator();

        $ids = array_map(static fn (): string => $generator->generate(), range(1, 100));

        self::assertCount(100, array_unique($ids));
    }
}
