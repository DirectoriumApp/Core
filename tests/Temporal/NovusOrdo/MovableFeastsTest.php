<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal\NovusOrdo;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdo\MovableFeasts;
use Directorium\Core\Temporal\Season;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The Novus-Ordo movable solemnities of the Lord in Ordinary Time (#108): the four the reform
 * keeps — the Most Holy Trinity, the Body and Blood of Christ, the Sacred Heart, and Christ the
 * King — each re-placed and re-titled, and NOT the traditional Holy Name / January Holy Family
 * the reform dropped. Offices are read from the real shipped Novus-Ordo data. Easter 2024 =
 * 31 March; the First Sunday of Advent 2024 = 1 December.
 */
final class MovableFeastsTest extends TestCase
{
    private const EDITION = 'roman-novus-ordo-2002';

    private function feasts(int $year): MovableFeasts
    {
        return MovableFeasts::forYear($year, Corpus::default(), self::EDITION);
    }

    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /** The Most Holy Trinity — the Sunday after Pentecost (Easter+56). */
    public function testTrinitySunday(): void
    {
        $feasts = $this->feasts(2024);

        self::assertSame('2024-05-26', $feasts->trinity()->format('Y-m-d'));
        $trinity = $feasts->on(self::utc('2024-05-26'));
        self::assertNotNull($trinity);
        self::assertSame('roman:temporale:paschal:trinity-sunday', $trinity->id()->toString());
        self::assertSame('Sanctissimae Trinitatis', $trinity->latinName());
        self::assertSame('feast', $trinity->kind()->value());
        self::assertSame('white', $trinity->colour()->base()->value());
        self::assertSame(Season::ORDINARY_TIME, $trinity->season()->value());
    }

    /** The Most Holy Body and Blood of Christ — the Thursday after Trinity (Easter+60) in the universal calendar. */
    public function testCorpusChristi(): void
    {
        $feasts = $this->feasts(2024);

        self::assertSame('2024-05-30', $feasts->corpusChristi()->format('Y-m-d'));
        self::assertSame('Thu', $feasts->corpusChristi()->format('D'));
        $corpus = $feasts->on(self::utc('2024-05-30'));
        self::assertSame('roman:temporale:paschal:corpus-christi', $corpus->id()->toString());
        self::assertSame('Sanctissimi Corporis et Sanguinis Christi', $corpus->latinName());
        self::assertSame('white', $corpus->colour()->base()->value());
    }

    /** The Most Sacred Heart — the Friday of the third week after Pentecost (Easter+68). */
    public function testSacredHeart(): void
    {
        $feasts = $this->feasts(2024);

        self::assertSame('2024-06-07', $feasts->sacredHeart()->format('Y-m-d'));
        self::assertSame('Fri', $feasts->sacredHeart()->format('D'));
        $sacredHeart = $feasts->on(self::utc('2024-06-07'));
        self::assertSame('roman:temporale:paschal:sacred-heart', $sacredHeart->id()->toString());
        self::assertSame('Sacratissimi Cordis Iesu', $sacredHeart->latinName());
    }

    /** Christ the King — the last Sunday of Ordinary Time, the Sunday before Advent (not October). */
    public function testChristTheKing(): void
    {
        $feasts = $this->feasts(2024);

        self::assertSame('2024-11-24', $feasts->christTheKing()->format('Y-m-d'));
        self::assertSame('Sun', $feasts->christTheKing()->format('D'));
        $christTheKing = $feasts->on(self::utc('2024-11-24'));
        self::assertSame('roman:temporale:ordinary-time:christ-the-king', $christTheKing->id()->toString());
        self::assertSame('Domini Nostri Iesu Christi Universorum Regis', $christTheKing->latinName());
        self::assertSame('white', $christTheKing->colour()->base()->value());
        self::assertSame(Season::ORDINARY_TIME, $christTheKing->season()->value());
    }

    /**
     * The reform keeps exactly four movable solemnities of the Lord — and none of the traditional
     * January movable feasts (the Holy Name and the Holy Family, the latter now the Sunday within
     * the Christmas octave). No feast falls on an ordinary Ordinary-Time day or in early January.
     */
    public function testKeepsExactlyFourAndDropsTheJanuaryFeasts(): void
    {
        $feasts = $this->feasts(2024);

        self::assertCount(4, $feasts->feasts());
        self::assertNull($feasts->on(self::utc('2024-01-07')), 'no January Holy Family in the movable set');
        self::assertNull($feasts->on(self::utc('2024-01-14')), 'no January Holy Name in the movable set');
        self::assertNull($feasts->on(self::utc('2024-07-15')), 'no movable feast on an ordinary July weekday');
    }

    public function testRejectsPreGregorianYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MovableFeasts::forYear(1580, Corpus::default(), self::EDITION);
    }
}
