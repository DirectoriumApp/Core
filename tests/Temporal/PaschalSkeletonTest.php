<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Temporal;

use Introibo\Core\Temporal\Computus;
use Introibo\Core\Temporal\PaschalSkeleton;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaschalSkeletonTest extends TestCase
{
    /**
     * Marquee anchors for three sample years, verified independently. 2024 is a
     * leap year whose pre-Lent anchors cross 29 February.
     *
     * @dataProvider sampleAnchors
     */
    public function testAnchorDate(int $year, string $anchor, string $expected): void
    {
        self::assertSame($expected, PaschalSkeleton::forYear($year)->date($anchor)->format('Y-m-d'));
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public function sampleAnchors(): array
    {
        return [
            // 1962 — engine baseline (Easter 22 Apr).
            '1962 septuagesima' => [1962, 'septuagesima', '1962-02-18'],
            '1962 ash-wednesday' => [1962, 'ash-wednesday', '1962-03-07'],
            '1962 palm-sunday' => [1962, 'palm-sunday', '1962-04-15'],
            '1962 easter' => [1962, 'easter', '1962-04-22'],
            '1962 ascension' => [1962, 'ascension', '1962-05-31'],
            '1962 pentecost' => [1962, 'pentecost', '1962-06-10'],
            '1962 trinity-sunday' => [1962, 'trinity-sunday', '1962-06-17'],
            '1962 corpus-christi' => [1962, 'corpus-christi', '1962-06-21'],
            // 2024 — leap year (Easter 31 Mar).
            '2024 septuagesima' => [2024, 'septuagesima', '2024-01-28'],
            '2024 ash-wednesday' => [2024, 'ash-wednesday', '2024-02-14'],
            '2024 palm-sunday' => [2024, 'palm-sunday', '2024-03-24'],
            '2024 easter' => [2024, 'easter', '2024-03-31'],
            '2024 ascension' => [2024, 'ascension', '2024-05-09'],
            '2024 pentecost' => [2024, 'pentecost', '2024-05-19'],
            '2024 corpus-christi' => [2024, 'corpus-christi', '2024-05-30'],
            // 2025 (Easter 20 Apr).
            '2025 ash-wednesday' => [2025, 'ash-wednesday', '2025-03-05'],
            '2025 easter' => [2025, 'easter', '2025-04-20'],
            '2025 ascension' => [2025, 'ascension', '2025-05-29'],
            '2025 pentecost' => [2025, 'pentecost', '2025-06-08'],
            '2025 corpus-christi' => [2025, 'corpus-christi', '2025-06-19'],
        ];
    }

    /**
     * Independent check that the offsets land on the right weekday: this catches
     * an off-by-one that same-table arithmetic would hide.
     *
     * @dataProvider expectedWeekdays
     */
    public function testAnchorFallsOnExpectedWeekday(string $anchor, string $weekday): void
    {
        foreach ([1962, 2024, 2025] as $year) {
            $date = PaschalSkeleton::forYear($year)->date($anchor);
            self::assertSame($weekday, $date->format('l'), "$anchor $year");
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function expectedWeekdays(): array
    {
        return [
            'septuagesima' => ['septuagesima', 'Sunday'],
            'quinquagesima' => ['quinquagesima', 'Sunday'],
            'ash-wednesday' => ['ash-wednesday', 'Wednesday'],
            'lent-1' => ['lent-1', 'Sunday'],
            'lent-ember-friday' => ['lent-ember-friday', 'Friday'],
            'passion-sunday' => ['passion-sunday', 'Sunday'],
            'palm-sunday' => ['palm-sunday', 'Sunday'],
            'maundy-thursday' => ['maundy-thursday', 'Thursday'],
            'good-friday' => ['good-friday', 'Friday'],
            'holy-saturday' => ['holy-saturday', 'Saturday'],
            'easter' => ['easter', 'Sunday'],
            'low-sunday' => ['low-sunday', 'Sunday'],
            'rogation-monday' => ['rogation-monday', 'Monday'],
            'ascension' => ['ascension', 'Thursday'],
            'pentecost' => ['pentecost', 'Sunday'],
            'trinity-sunday' => ['trinity-sunday', 'Sunday'],
            'corpus-christi' => ['corpus-christi', 'Thursday'],
            'sacred-heart' => ['sacred-heart', 'Friday'],
        ];
    }

    /**
     * Leap-year handling: whatever the year, an anchor's day-distance from Easter
     * equals its offset — even when the span crosses 29 February.
     */
    public function testLeapYearDayCountsAreConstant(): void
    {
        $leap = PaschalSkeleton::forYear(2024); // Ash Wednesday crosses 29 Feb
        $common = PaschalSkeleton::forYear(2025);

        self::assertSame('2024-02-14', $leap->ashWednesday()->format('Y-m-d'));
        self::assertSame('2025-03-05', $common->ashWednesday()->format('Y-m-d'));

        self::assertSame(46, $leap->easter()->diff($leap->ashWednesday())->days);
        self::assertSame(46, $common->easter()->diff($common->ashWednesday())->days);
        self::assertSame(63, $leap->easter()->diff($leap->septuagesima())->days);
    }

    public function testAllReturnsEveryAnchor(): void
    {
        $all = PaschalSkeleton::forYear(1962)->all();

        self::assertSame(array_keys(PaschalSkeleton::offsets()), array_keys($all));
        self::assertSame('1962-04-22', $all['easter']->format('Y-m-d'));
    }

    public function testUnknownAnchorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaschalSkeleton::forYear(1962)->date('major-rogation');
    }

    public function testNamedAccessorsMatchSlugLookups(): void
    {
        $skeleton = PaschalSkeleton::forYear(2025);

        self::assertEquals($skeleton->date('septuagesima'), $skeleton->septuagesima());
        self::assertEquals($skeleton->date('ash-wednesday'), $skeleton->ashWednesday());
        self::assertEquals($skeleton->date('ascension'), $skeleton->ascension());
        self::assertEquals($skeleton->date('pentecost'), $skeleton->pentecost());
        self::assertEquals($skeleton->date('corpus-christi'), $skeleton->corpusChristi());
    }

    public function testFromEasterMatchesForYear(): void
    {
        $fromYear = PaschalSkeleton::forYear(1962);
        $fromEaster = PaschalSkeleton::fromEaster(Computus::gregorianEaster(1962));

        self::assertEquals($fromYear->all(), $fromEaster->all());
    }
}
