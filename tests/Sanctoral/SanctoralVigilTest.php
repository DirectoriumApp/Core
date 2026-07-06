<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use DateTimeImmutable;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * The surviving 1960 sanctoral vigils (#27): kept on the day before their feast,
 * violet, each linking to its parent feast. Suppressed vigils are simply not in
 * the data, so they are never emitted.
 */
final class SanctoralVigilTest extends TestCase
{
    public function testEveryVigilIsPlacedTheDayBeforeItsParentFeast(): void
    {
        $calendar = SanctoralCalendar::forYear(2025);
        $dateById = self::datesById($calendar);
        $vigils = self::vigils($calendar);

        self::assertNotEmpty($vigils, 'at least one vigil is seeded');

        foreach ($vigils as $ymd => $vigil) {
            $parentId = $vigil->vigilOfId();
            self::assertNotNull($parentId, "$ymd has a parent id");

            $parent = $parentId->toString();
            self::assertArrayHasKey($parent, $dateById, "parent feast $parent is placed");

            $dayAfterVigil = (new DateTimeImmutable($ymd))->modify('+1 day')->format('Y-m-d');
            self::assertSame($dateById[$parent], $dayAfterVigil, "$ymd is the eve of $parent");
        }
    }

    public function testTheFourSurvivingVigilsArePresent(): void
    {
        $ids = array_map(
            static fn (SanctoralObservance $v): string => $v->id()->toString(),
            array_values(self::vigils(SanctoralCalendar::forYear(2025)))
        );

        self::assertContains('roman:sanctorale:ioannes-baptista:vigilia', $ids);
        self::assertContains('roman:sanctorale:petrus-paulus:vigilia', $ids);
        self::assertContains('roman:sanctorale:laurentius:vigilia', $ids);
        self::assertContains('roman:sanctorale:assumptio:vigilia', $ids);
        self::assertCount(4, $ids);
    }

    public function testVigilsAreVioletAndCarryTheVigilKind(): void
    {
        foreach (self::vigils(SanctoralCalendar::forYear(2025)) as $vigil) {
            self::assertSame('vigil', $vigil->kind()->value(), $vigil->id()->toString());
            self::assertSame('violet', $vigil->colour()->base()->value(), $vigil->id()->toString());
        }
    }

    /** The one III-class vigil (St Lawrence) coexists with the II-class ones. */
    public function testVigilRankIsPerEntryNotHardcoded(): void
    {
        $ranksById = [];
        foreach (self::vigils(SanctoralCalendar::forYear(2025)) as $vigil) {
            $ranksById[$vigil->id()->toString()] = $vigil->rank()->label();
        }

        self::assertSame('II', $ranksById['roman:sanctorale:ioannes-baptista:vigilia']);
        self::assertSame('III', $ranksById['roman:sanctorale:laurentius:vigilia']);
    }

    public function testANonVigilOfficeHasNoParentLink(): void
    {
        $assumption = SanctoralCalendar::forYear(2025)->on(new DateTimeImmutable('2025-08-15'));

        self::assertCount(1, $assumption);
        self::assertFalse($assumption[0]->isVigil());
        self::assertNull($assumption[0]->vigilOfId());
    }

    /**
     * @return array<string, string> observance id => 'Y-m-d'
     */
    private static function datesById(SanctoralCalendar $calendar): array
    {
        $dateById = [];
        foreach ($calendar->all() as $ymd => $offices) {
            foreach ($offices as $office) {
                $dateById[$office->id()->toString()] = $ymd;
            }
        }

        return $dateById;
    }

    /**
     * @return array<string, SanctoralObservance> 'Y-m-d' => the vigil placed there
     */
    private static function vigils(SanctoralCalendar $calendar): array
    {
        $vigils = [];
        foreach ($calendar->all() as $ymd => $offices) {
            foreach ($offices as $office) {
                if ($office->isVigil()) {
                    $vigils[$ymd] = $office;
                }
            }
        }

        return $vigils;
    }
}
