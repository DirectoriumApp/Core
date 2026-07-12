<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Decree;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Decree\DecreeSet;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralData;
use PHPUnit\Framework\TestCase;

/**
 * The dated decrees an edition ships, loaded from the real corpus (#366). The
 * traditional editions carry none; the Novus Ordo 2002 snapshot carries the two real
 * post-2002 decrees (Mary Magdalene → feast, 2016; the Mother of the Church movable
 * memorial, 2018), each applied on or after its effective date.
 */
final class DecreeSetTest extends TestCase
{
    private const NO_DIR = 'roman-novus-ordo-2002';
    private const MAGDALENE = 'roman:sanctorale:maria-magdalena';
    private const MATER_ECCLESIAE = 'roman:sanctorale:maria-mater-ecclesiae';

    private static function noSet(): DecreeSet
    {
        return DecreeSet::forEdition(Corpus::default(), self::NO_DIR);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }

    private static function rankOf(SanctoralData $data, string $id): ?int
    {
        foreach ($data->entries() as $entry) {
            if ($entry->identity()->id()->toString() === $id) {
                return $entry->rank()->ordinal();
            }
        }

        return null;
    }

    public function testTheTraditionalEditionsShipNoDecrees(): void
    {
        self::assertTrue(DecreeSet::forEdition(Corpus::default(), 'roman-rubricae-1960')->isEmpty());
        self::assertTrue(DecreeSet::forEdition(Corpus::default(), 'roman-divino-afflatu')->isEmpty());
        self::assertTrue(DecreeSet::forEdition(Corpus::default(), 'roman-rubricae-1955')->isEmpty());
    }

    public function testLoadsTheNovusOrdoDecreesInEffectiveOrder(): void
    {
        $decrees = self::noSet()->all();

        self::assertCount(2, $decrees);
        self::assertSame('2016-06-03-mariae-magdalenae-festum', $decrees[0]->id());
        self::assertSame('Apostolorum Apostola', $decrees[0]->title());
        self::assertSame('2016-06-03', $decrees[0]->effective()->format('Y-m-d'));
        self::assertSame('2018-02-11-mater-ecclesiae', $decrees[1]->id());
        self::assertSame('Ecclesia Mater', $decrees[1]->title());
    }

    public function testAppliesTheMagdaleneRerankGatedByYear(): void
    {
        $base = new CorpusSanctoralData(Corpus::default(), self::NO_DIR);
        $set = self::noSet();

        self::assertSame(3, self::rankOf($set->applyTo($base, 2015), self::MAGDALENE), 'a memorial before 2016');
        self::assertSame(2, self::rankOf($set->applyTo($base, 2016), self::MAGDALENE), 'a feast from the decree year');
        self::assertSame(2, self::rankOf($set->applyTo($base, 2025), self::MAGDALENE));
    }

    public function testSurfacesTheMovableMemorialOnlyFromItsEffectiveDate(): void
    {
        $set = self::noSet();

        // Pentecost Monday 2017 (5 June) is before the 2018 decree — no movable office.
        self::assertNull($set->officesFor(2017)->on(self::utc('2017-06-05')));

        // From 2018 the memorial appears on the Monday after Pentecost (9 June 2025).
        $office = $set->officesFor(2025)->on(self::utc('2025-06-09'));
        self::assertNotNull($office);
        self::assertSame(self::MATER_ECCLESIAE, $office->id()->toString());
        self::assertSame(3, $office->rank()->ordinal());
        self::assertSame('white', $office->colour()->base()->value());
    }

    public function testAnEmptySetIsInertOnBothAxes(): void
    {
        $empty = DecreeSet::forEdition(Corpus::default(), 'roman-rubricae-1960');
        $base = new CorpusSanctoralData(Corpus::default(), 'roman-rubricae-1960');

        self::assertSame($base, $empty->applyTo($base, 2025), 'the base is returned unchanged');
        self::assertTrue($empty->officesFor(2025)->isEmpty());
    }
}
