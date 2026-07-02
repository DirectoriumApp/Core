<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Temporal\MovableFeasts;
use Introibo\Core\Temporal\PaschalSkeleton;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MovableFeastsTest extends TestCase
{
    public function testCivilAnchoredDates(): void
    {
        $feasts = MovableFeasts::forYear(2025);

        self::assertSame('2025-01-05', $feasts->holyName()->format('Y-m-d'));   // Sunday of 2–5 Jan
        self::assertSame('2025-01-12', $feasts->holyFamily()->format('Y-m-d')); // Sunday of 7–13 Jan
        self::assertSame('2025-10-26', $feasts->christTheKing()->format('Y-m-d')); // last Sunday of October
    }

    public function testEasterAnchoredDatesMatchThePaschalSkeleton(): void
    {
        $feasts = MovableFeasts::forYear(2025);
        $skeleton = PaschalSkeleton::forYear(2025);

        self::assertEquals($skeleton->date('trinity-sunday'), $feasts->trinitySunday());
        self::assertEquals($skeleton->date('corpus-christi'), $feasts->corpusChristi());
        self::assertEquals($skeleton->date('sacred-heart'), $feasts->sacredHeart());
    }

    /**
     * The edge case the issue calls out: when no Sunday falls between 2 and 5
     * January, the Most Holy Name is kept on 2 January (2023's 1 January is a
     * Sunday, so 2–5 January is Monday–Thursday).
     */
    public function testHolyNameFallsOnJanuary2WhenNoSundayIsAvailable(): void
    {
        $holyName = MovableFeasts::forYear(2023)->holyName();

        self::assertSame('2023-01-02', $holyName->format('Y-m-d'));
        self::assertSame('Monday', $holyName->format('l'));
    }

    /**
     * Across every year the movable feasts land on their proper weekday and within
     * their placement window — the Sunday-ruled ones on a Sunday.
     */
    public function testPlacementInvariantsHold(): void
    {
        for ($year = 1583; $year <= 2200; $year++) {
            $feasts = MovableFeasts::forYear($year);

            self::assertSame('7', $feasts->trinitySunday()->format('N'), "Trinity $year");
            self::assertSame('7', $feasts->holyFamily()->format('N'), "Holy Family $year");
            self::assertSame('7', $feasts->christTheKing()->format('N'), "Christ the King $year");
            self::assertSame('4', $feasts->corpusChristi()->format('N'), "Corpus Christi $year"); // Thursday
            self::assertSame('5', $feasts->sacredHeart()->format('N'), "Sacred Heart $year");     // Friday

            $holyFamily = $feasts->holyFamily()->format('m-d');
            self::assertTrue($holyFamily >= '01-07' && $holyFamily <= '01-13', "Holy Family window $year");

            $christTheKing = $feasts->christTheKing()->format('m-d');
            self::assertTrue($christTheKing >= '10-25' && $christTheKing <= '10-31', "Christ the King window $year");
        }
    }

    public function testFeastsAreSixDistinctDatedObservances(): void
    {
        $feasts = MovableFeasts::forYear(2025)->feasts();

        self::assertCount(6, $feasts);
        self::assertSame(array_keys($feasts), array_values(array_unique(array_keys($feasts))));
        // Chronological order.
        $keys = array_keys($feasts);
        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys);
    }

    /**
     * @dataProvider theSixFeasts
     */
    public function testDayRealization(string $date, string $id, string $rank, string $latin): void
    {
        $obs = MovableFeasts::forYear(2025)->on(self::utc($date));
        self::assertNotNull($obs, $date);

        self::assertSame('roman:temporale:' . $id, $obs->id()->toString(), "$date id");
        self::assertSame('feast', $obs->kind()->value(), "$date kind");
        self::assertSame($rank, $obs->rank()->label(), "$date rank");
        self::assertSame('white', $obs->colour()->base()->value(), "$date colour");
        self::assertSame($latin, $obs->latinName(), "$date latin");
    }

    /**
     * The id column omits the constant `roman:temporale:` prefix.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public function theSixFeasts(): array
    {
        return [
            'Holy Name' =>
                ['2025-01-05', 'christmas:holy-name', 'II', 'In Festo Sanctissimi Nominis Iesu'],
            'Holy Family' =>
                ['2025-01-12', 'epiphany:holy-family', 'II', 'In Festo Sanctae Familiae Iesu, Mariae, Ioseph'],
            'Most Holy Trinity' =>
                ['2025-06-15', 'paschal:trinity-sunday', 'I', 'In Festo Sanctissimae Trinitatis'],
            'Corpus Christi' =>
                ['2025-06-19', 'paschal:corpus-christi', 'I', 'In Festo Sanctissimi Corporis Christi'],
            'Most Sacred Heart' =>
                ['2025-06-27', 'paschal:sacred-heart', 'I', 'In Festo Sacratissimi Cordis Iesu'],
            'Christ the King' =>
                ['2025-10-26', 'month-computed:christ-the-king', 'I', 'In Festo Domini Nostri Iesu Christi Regis'],
        ];
    }

    public function testOnReturnsNullWhenNoMovableFeastFalls(): void
    {
        $feasts = MovableFeasts::forYear(2025);

        self::assertNotNull($feasts->on(self::utc('2025-06-15')), 'Trinity Sunday');
        self::assertNull($feasts->on(self::utc('2025-06-16')), 'the Monday after Trinity');
    }

    public function testPreGregorianYearIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MovableFeasts::forYear(1582);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
