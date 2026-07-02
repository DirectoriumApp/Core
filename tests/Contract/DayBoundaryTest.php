<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\contract;

/**
 * The liturgical day begins at First Vespers the evening before and vigils
 * attach to the morrow, so the contract must express the boundary (Epic #52,
 * #57). `secondVespers` reports how this day's evening concurs with the next;
 * a vigil sits on its own preceding date naming the feast it anticipates; and
 * `firstVespers` is a reserved day-level slot the Office layer will fill.
 *
 * The Vigil of the Assumption (14 Aug) exercises all three: it is a `vigil`
 * naming the Assumption, its evening yields to the first-class feast of the
 * morrow, and the Assumption itself is celebrated on 15 Aug — not in this day.
 */
final class DayBoundaryTest extends TestCase
{
    /** On the eve of a first-class feast the evening belongs to the following office day. */
    public function testEveningCanBelongToTheFollowingOfficeDay(): void
    {
        $secondVespers = contract(self::utc('2025-08-14'))['secondVespers'];

        self::assertIsArray($secondVespers);
        self::assertTrue($secondVespers['favoursFollowing']);
        self::assertSame('following-commem-preceding', $secondVespers['outcome']);
    }

    /** First Vespers of this evening is a reserved day-level slot (Office layer), null in v1.0. */
    public function testFirstVespersIsAReservedNullSlot(): void
    {
        $day = contract(self::utc('2025-08-14'));

        self::assertArrayHasKey('firstVespers', $day);
        self::assertNull($day['firstVespers']);
    }

    /** A vigil sits on its own date naming the feast it anticipates; that feast is on the morrow, not here. */
    public function testVigilIsAttachedToTheDayItAnticipates(): void
    {
        $vigil = self::firstOffice('2025-08-14', 'celebration');
        self::assertSame('vigil', $vigil['kind']);
        self::assertSame('roman:sanctorale:assumptio', $vigil['vigilOf']);

        self::assertNotContains('roman:sanctorale:assumptio', self::allIds(contract(self::utc('2025-08-14'))));
        self::assertContains('roman:sanctorale:assumptio', self::allIds(contract(self::utc('2025-08-15'))));
    }

    /**
     * @param array<string, mixed> $day
     *
     * @return array<int, mixed>
     */
    private static function allIds(array $day): array
    {
        $ids = [];
        foreach (['celebration', 'commemoration', 'displaced', 'tempora'] as $role) {
            $offices = $day[$role];
            self::assertIsArray($offices);
            foreach ($offices as $office) {
                self::assertIsArray($office);
                $ids[] = $office['id'];
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstOffice(string $ymd, string $role): array
    {
        $day = contract(self::utc($ymd));
        self::assertArrayHasKey($role, $day);
        $offices = $day[$role];
        self::assertIsArray($offices);
        self::assertArrayHasKey(0, $offices);
        $office = $offices[0];
        self::assertIsArray($office);

        /** @var array<string, mixed> $office */
        return $office;
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
