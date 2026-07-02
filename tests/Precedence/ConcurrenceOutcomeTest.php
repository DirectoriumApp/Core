<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use Introibo\Core\Precedence\ConcurrenceOutcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConcurrenceOutcomeTest extends TestCase
{
    public function testFactoriesAndValues(): void
    {
        self::assertSame('full-of-preceding', ConcurrenceOutcome::fullOfPreceding()->value());
        self::assertSame(
            'preceding-commem-following',
            ConcurrenceOutcome::precedingWithCommemorationOfFollowing()->value()
        );
        self::assertSame(
            'following-commem-preceding',
            ConcurrenceOutcome::followingWithCommemorationOfPreceding()->value()
        );
        self::assertSame('full-of-following', ConcurrenceOutcome::fullOfFollowing()->value());
    }

    public function testFavoursFollowing(): void
    {
        self::assertTrue(ConcurrenceOutcome::fullOfFollowing()->favoursFollowing());
        self::assertTrue(ConcurrenceOutcome::followingWithCommemorationOfPreceding()->favoursFollowing());
        self::assertFalse(ConcurrenceOutcome::fullOfPreceding()->favoursFollowing());
        self::assertFalse(ConcurrenceOutcome::precedingWithCommemorationOfFollowing()->favoursFollowing());
    }

    public function testEqualsAndRejectsUnknown(): void
    {
        self::assertTrue(
            ConcurrenceOutcome::fullOfFollowing()->equals(ConcurrenceOutcome::fromString('full-of-following'))
        );
        self::assertFalse(ConcurrenceOutcome::fullOfFollowing()->equals(ConcurrenceOutcome::fullOfPreceding()));

        $this->expectException(InvalidArgumentException::class);
        ConcurrenceOutcome::fromString('split');
    }
}
