<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Discipline;

use Directorium\Core\Discipline\Abstinence;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The abstinence grades (none / partial / full) and their severity order (#248), by
 * which a day meeting several rules keeps the strictest.
 */
final class AbstinenceTest extends TestCase
{
    public function testFactoriesAndValues(): void
    {
        self::assertSame('none', Abstinence::none()->value());
        self::assertSame('partial', Abstinence::partial()->value());
        self::assertSame('full', Abstinence::full()->value());
        self::assertSame('full', (string) Abstinence::fromString('full'));
    }

    public function testFromStringRejectsUnknownGrade(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Abstinence::fromString('half');
    }

    public function testSeverityOrder(): void
    {
        self::assertTrue(Abstinence::full()->isStricterThan(Abstinence::partial()));
        self::assertTrue(Abstinence::partial()->isStricterThan(Abstinence::none()));
        self::assertTrue(Abstinence::full()->isStricterThan(Abstinence::none()));

        self::assertFalse(Abstinence::none()->isStricterThan(Abstinence::full()));
        self::assertFalse(Abstinence::partial()->isStricterThan(Abstinence::partial()));
    }

    public function testApplies(): void
    {
        self::assertFalse(Abstinence::none()->applies());
        self::assertTrue(Abstinence::partial()->applies());
        self::assertTrue(Abstinence::full()->applies());
    }

    public function testEquality(): void
    {
        self::assertTrue(Abstinence::full()->equals(Abstinence::fromString('full')));
        self::assertFalse(Abstinence::full()->equals(Abstinence::partial()));
    }
}
