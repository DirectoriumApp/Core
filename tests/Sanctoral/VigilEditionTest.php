<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Sanctoral;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

/**
 * The pre-1955 (Divino Afflatu) sanctoral VIGILS reintroduced for the 1954 edition (#66).
 *
 * A vigil is placement DATA — the day before its feast, at the vigil office-grade — exactly
 * the mechanism v0.1.0 already used for the four vigils 1962 kept. #66 restores the nine the
 * 1955 simplification suppressed (the seven remaining Apostles, All Saints, the Immaculate
 * Conception) as 1954-only observances, and adds the 1954 grade to the four survivors.
 *
 * These tests prove the restored vigils land on the right day at the vigil grade, that
 * {@see vigilOfId} threads to the anticipated feast, that a kept vigil wears the 1954 grade
 * (not its 1960 class), and that the 1960 edition places none of the suppressed vigils even
 * though the shared identity now lists them. The Sunday-anticipation and Matthias-bissextile
 * RESOLUTION rules are deferred to the 1954 precedence engine (#67); this is data only.
 */
final class VigilEditionTest extends TestCase
{
    private const DIVINO_AFFLATU = 'roman-divino-afflatu';

    private function nineteenFiftyFour(): SanctoralCalendar
    {
        return SanctoralCalendar::forYear(1954, new CorpusSanctoralData(null, self::DIVINO_AFFLATU));
    }

    /**
     * @return list<SanctoralObservance>
     */
    private function on(SanctoralCalendar $calendar, string $date): array
    {
        return $calendar->on(new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    /**
     * @param list<SanctoralObservance> $offices
     */
    private function observanceFor(array $offices, string $id): ?SanctoralObservance
    {
        foreach ($offices as $office) {
            if ($office->id()->toString() === $id) {
                return $office;
            }
        }

        return null;
    }

    public function testARestoredApostleVigilIsPlacedAtVigilGradeLinkedToItsFeast(): void
    {
        // Vigil of St Andrew — the day before his feast (Nov 30), so Nov 29.
        $vigil = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-11-29'),
            'roman:sanctorale:andreas:vigilia'
        );

        self::assertInstanceOf(SanctoralObservance::class, $vigil);
        self::assertTrue($vigil->isVigil());
        self::assertSame('vigil', $vigil->kind()->value());
        self::assertSame('vigilia', $vigil->legacyRank()->value());
        self::assertSame(4, $vigil->rank()->ordinal());
        self::assertSame('violet', $vigil->colour()->base()->value());
        self::assertSame('In Vigilia S. Andreae Apostoli', $vigil->latinName());
        self::assertNotNull($vigil->vigilOfId());
        self::assertSame('roman:sanctorale:andreas', $vigil->vigilOfId()->toString());
    }

    public function testTheImmaculateConceptionVigilIsRestored(): void
    {
        // A second restored vigil (AC: cover two) — Marian, not apostolic. Dec 8 feast → Dec 7.
        $vigil = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-12-07'),
            'roman:sanctorale:immaculata-conceptio:vigilia'
        );

        self::assertInstanceOf(SanctoralObservance::class, $vigil);
        self::assertTrue($vigil->isVigil());
        self::assertSame('vigilia', $vigil->legacyRank()->value());
        self::assertSame('violet', $vigil->colour()->base()->value());
        // The Missal's vigil heading, distinct from the feast's ablative form.
        self::assertSame('In Vigilia Immaculatae Conceptionis B.M.V.', $vigil->latinName());
        self::assertSame('roman:sanctorale:immaculata-conceptio', $vigil->vigilOfId()->toString());
    }

    public function testTheMatthiasVigilAnchorsAtFebruaryTwentyThree(): void
    {
        // 1954 is a common year, so the vigil sits at its nominal anchor, Feb 23. The leap-year
        // shift (feast → Feb 25, vigil → Feb 24, the doubled bis sextum) is a #67 resolution rule.
        $vigil = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-02-23'),
            'roman:sanctorale:matthias:vigilia'
        );

        self::assertInstanceOf(SanctoralObservance::class, $vigil);
        self::assertSame('roman:sanctorale:matthias', $vigil->vigilOfId()->toString());
    }

    public function testAKeptVigilWearsTheLegacyGradeNotItsNineteenSixtyClass(): void
    {
        // The Vigil of St John the Baptist survives into 1962, but the editions grade it
        // differently: a floor-tier vigil (class IV) in 1954, a II-class vigil in 1960. The
        // shared identity is one record; the per-edition attributes diverge.
        $vigil1954 = $this->observanceFor(
            $this->on($this->nineteenFiftyFour(), '1954-06-23'),
            'roman:sanctorale:ioannes-baptista:vigilia'
        );
        self::assertInstanceOf(SanctoralObservance::class, $vigil1954);
        self::assertSame('vigilia', $vigil1954->legacyRank()->value());
        self::assertSame(4, $vigil1954->rank()->ordinal());

        $vigil1962 = $this->observanceFor(
            $this->on(SanctoralCalendar::forYear(1962), '1962-06-23'),
            'roman:sanctorale:ioannes-baptista:vigilia'
        );
        self::assertInstanceOf(SanctoralObservance::class, $vigil1962);
        self::assertNull($vigil1962->legacyRank(), 'The 1960 edition carries no legacy grade.');
        self::assertSame(2, $vigil1962->rank()->ordinal());
    }

    /**
     * Every one of the 13 authored vigils, checked exhaustively: on its 1954 date it is a
     * violet vigil at the vigil grade, links to the right feast, and bears the exact Latin
     * title. A swapped date or transposed title on any single vigil fails here — the aggregate
     * count and "has some legacyRank" checks elsewhere would not catch it.
     *
     * @return array<string, array{0: int, 1: int, 2: string, 3: string}>
     */
    public static function vigilProvider(): array
    {
        // label => [month, day, anticipated-feast slug, Latin title]. The vigil's own id is
        // "<feast slug>:vigilia" — except St John Baptist, whose feast slug differs from the stem.
        return [
            'Andrew' => [11, 29, 'andreas', 'In Vigilia S. Andreae Apostoli'],
            'Thomas' => [12, 20, 'thomas-apostolus', 'In Vigilia S. Thomae Apostoli'],
            'Matthias' => [2, 23, 'matthias', 'In Vigilia S. Matthiae Apostoli'],
            'James' => [7, 24, 'iacobus', 'In Vigilia S. Iacobi Apostoli'],
            'Bartholomew' => [8, 23, 'bartholomaeus', 'In Vigilia S. Bartholomaei Apostoli'],
            'Matthew' => [9, 20, 'matthaeus', 'In Vigilia S. Matthaei Apostoli et Evangelistae'],
            'Simon & Jude' => [10, 27, 'simon-et-iudas', 'In Vigilia Ss. Simonis et Iudae Apostolorum'],
            'All Saints' => [10, 31, 'omnes-sancti', 'In Vigilia Omnium Sanctorum'],
            'Immaculate Conception' => [12, 7, 'immaculata-conceptio', 'In Vigilia Immaculatae Conceptionis B.M.V.'],
            'John the Baptist' => [6, 23, 'nativitas-ioannis-baptistae', 'In Vigilia S. Ioannis Baptistae'],
            'Peter & Paul' => [6, 28, 'petrus-paulus', 'In Vigilia Ss. Petri et Pauli Apostolorum'],
            'Lawrence' => [8, 9, 'laurentius', 'In Vigilia S. Laurentii Martyris'],
            'Assumption' => [8, 14, 'assumptio', 'In Vigilia Assumptionis B.M.V.'],
        ];
    }

    /**
     * @dataProvider vigilProvider
     */
    public function testEveryAuthoredVigilLandsCorrectly(int $month, int $day, string $feastSlug, string $title): void
    {
        $vigilOf = 'roman:sanctorale:' . $feastSlug;
        // The vigil's stem is the feast slug, except St John Baptist (feast
        // nativitas-ioannis-baptistae, vigil stem ioannes-baptista).
        $stem = $feastSlug === 'nativitas-ioannis-baptistae' ? 'ioannes-baptista' : $feastSlug;
        $id = 'roman:sanctorale:' . $stem . ':vigilia';

        $date = sprintf('1954-%02d-%02d', $month, $day);
        $vigil = $this->observanceFor($this->on($this->nineteenFiftyFour(), $date), $id);

        self::assertInstanceOf(SanctoralObservance::class, $vigil, sprintf('%s not placed on %s', $id, $date));
        self::assertTrue($vigil->isVigil(), $id);
        self::assertSame('vigilia', $vigil->legacyRank()->value(), $id);
        self::assertSame(4, $vigil->rank()->ordinal(), $id);
        self::assertSame('violet', $vigil->colour()->base()->value(), $id);
        self::assertSame($vigilOf, $vigil->vigilOfId()->toString(), $id);
        self::assertSame($title, $vigil->latinName(), $id);
    }

    public function testTheNineteenSixtyEditionDoesNotPlaceASuppressedVigil(): void
    {
        // St Andrew's vigil is 1954-only: the 1955 reform suppressed it, so it carries no
        // roman-rubricae-1960 block and the 1960 edition places nothing on Nov 29 for it —
        // even though the shared identity now lists it (the cross-edition union).
        self::assertNull(
            $this->observanceFor(
                $this->on(SanctoralCalendar::forYear(1962), '1962-11-29'),
                'roman:sanctorale:andreas:vigilia'
            ),
            'The suppressed St Andrew vigil must not be placed in the 1960 edition.'
        );
    }
}
