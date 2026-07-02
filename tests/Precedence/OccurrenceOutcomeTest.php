<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use Introibo\Core\Precedence\OccurrenceOutcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OccurrenceOutcomeTest extends TestCase
{
    public function testFactoriesAndPredicates(): void
    {
        self::assertTrue(OccurrenceOutcome::transfer()->isTransfer());
        self::assertTrue(OccurrenceOutcome::commemorate()->isCommemoration());
        self::assertTrue(OccurrenceOutcome::omit()->isOmission());

        self::assertFalse(OccurrenceOutcome::omit()->isTransfer());
        self::assertFalse(OccurrenceOutcome::transfer()->isCommemoration());
    }

    public function testValuesAndEquals(): void
    {
        self::assertSame('transfer', OccurrenceOutcome::transfer()->value());
        self::assertSame('commemorate', OccurrenceOutcome::fromString('commemorate')->value());
        self::assertTrue(OccurrenceOutcome::omit()->equals(OccurrenceOutcome::fromString('omit')));
        self::assertFalse(OccurrenceOutcome::omit()->equals(OccurrenceOutcome::transfer()));
    }

    public function testRejectsUnknownOutcome(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OccurrenceOutcome::fromString('reassign');
    }
}
