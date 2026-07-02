<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use Introibo\Core\Temporal\TemporalArchetype;
use Introibo\Core\Temporal\TemporalAttributes;
use Introibo\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The temporal-archetype read seam (#42): the per-office kind, rank, colour, and
 * Latin name template read from the corpus and joined by archetype key. The exact
 * office of every day is exercised end to end by the six block-filler tests (and
 * locked byte-for-byte by the golden fixture); this pins the reader's own join and
 * the name renderer, whose feria composition must match {@see TemporalCalendar::feriaLatin()}.
 */
final class TemporalAttributesTest extends TestCase
{
    public function testJoinsIdentityKindWithEditionRankAndColour(): void
    {
        $nativity = TemporalAttributes::default()->archetype('nativity');

        self::assertSame('feast', $nativity->kind()->value());
        self::assertSame(1, $nativity->rank()->ordinal());
        self::assertSame('white', $nativity->colour()->base()->value());
        self::assertFalse($nativity->colour()->roseAllowed());
    }

    public function testCarriesTheRosePrivilegeForGaudeteAndLaetare(): void
    {
        $gaudete = TemporalAttributes::default()->archetype('advent-sunday-gaudete');

        self::assertSame(2, $gaudete->rank()->ordinal());
        self::assertSame('violet', $gaudete->colour()->base()->value());
        self::assertTrue($gaudete->colour()->roseAllowed());
    }

    public function testGoodFridayKeepsThe1962BlackNotTheModernRed(): void
    {
        self::assertSame('black', TemporalAttributes::default()->archetype('good-friday')->colour()->base()->value());
    }

    public function testRendersAFixedNameVerbatim(): void
    {
        $date = TemporalCalendar::utcDate(2024, 12, 25);

        self::assertSame(
            'In Nativitate Domini',
            TemporalAttributes::default()->archetype('nativity')->renderName($date)
        );
    }

    public function testFillsTheOrdinalPlaceholderWithARomanNumeral(): void
    {
        $date = TemporalCalendar::utcDate(2024, 12, 8); // a Sunday

        self::assertSame(
            'Dominica II Adventus',
            TemporalAttributes::default()->archetype('advent-sunday')->renderName($date, 2)
        );
    }

    public function testComposesAFeriaNameFromTheWeekday(): void
    {
        $wednesday = TemporalCalendar::utcDate(2024, 1, 3);
        $saturday = TemporalCalendar::utcDate(2024, 1, 6);
        $ember = TemporalAttributes::default()->archetype('advent-ember');

        // feriaLatin numbers Monday as Feria II, so Wednesday is Feria IV.
        self::assertSame('Feria IV Quatuor Temporum Adventus', $ember->renderName($wednesday));
        // Saturday is named Sabbato, never "Feria VII".
        self::assertSame('Sabbato Quatuor Temporum Adventus', $ember->renderName($saturday));
    }

    public function testComposesAFeriaNameThatAlsoCarriesAnOrdinal(): void
    {
        $monday = TemporalCalendar::utcDate(2024, 1, 1);

        self::assertSame(
            'Feria II infra Hebdomadam II Adventus',
            TemporalAttributes::default()->archetype('advent-feria')->renderName($monday, 2)
        );
    }

    public function testReturnsTheSameArchetypeInstanceType(): void
    {
        self::assertInstanceOf(
            TemporalArchetype::class,
            TemporalAttributes::default()->archetype('easter')
        );
    }

    public function testThrowsForAnUndefinedArchetype(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no-such-archetype');

        TemporalAttributes::default()->archetype('no-such-archetype');
    }
}
