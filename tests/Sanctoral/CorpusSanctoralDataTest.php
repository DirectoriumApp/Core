<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Sanctoral;

use Introibo\Core\Corpus\Corpus;
use Introibo\Core\Sanctoral\CorpusSanctoralData;
use Introibo\Core\Sanctoral\SanctoralEntry;
use Introibo\Core\Tests\Fixture\SeedSanctoralData;
use PHPUnit\Framework\TestCase;

final class CorpusSanctoralDataTest extends TestCase
{
    public function testVersionComesFromTheManifest(): void
    {
        self::assertSame(
            Corpus::default()->corpusVersion(),
            (new CorpusSanctoralData())->version()
        );
    }

    public function testReconstructsAFeastWithItsAttributesAndCitations(): void
    {
        $ioseph = $this->byId()['roman:sanctorale:ioseph'] ?? null;
        self::assertInstanceOf(SanctoralEntry::class, $ioseph);

        self::assertSame(3, $ioseph->month());
        self::assertSame(19, $ioseph->day());
        self::assertSame('feast', $ioseph->identity()->kind()->value());
        self::assertSame(1, $ioseph->rank()->ordinal());
        self::assertSame('white', $ioseph->colour()->base()->value());
        self::assertFalse($ioseph->colour()->roseAllowed());
        self::assertSame('S. Ioseph Sponsi B.M.V. Confessoris', $ioseph->identity()->latinName());
        self::assertSame(['ioseph'], $ioseph->identity()->titulars());
        self::assertNull($ioseph->vigilOfId());

        // Provenance travels with the datum: name from the missal, facts from the rubrics.
        self::assertSame('mr-1920', $ioseph->citations()->for('names.la')->sourceKey());
        self::assertSame('rg-1960', $ioseph->citations()->for('rank')->sourceKey());
        self::assertSame('rg-1960', $ioseph->citations()->for('colour')->sourceKey());
        self::assertSame('rg-1960', $ioseph->citations()->for('month')->sourceKey());
        self::assertSame('rg-1960', $ioseph->citations()->for('day')->sourceKey());
    }

    public function testReconstructsAVigilWithItsParentLink(): void
    {
        $vigil = $this->byId()['roman:sanctorale:laurentius:vigilia'] ?? null;
        self::assertInstanceOf(SanctoralEntry::class, $vigil);

        self::assertSame(8, $vigil->month());
        self::assertSame(9, $vigil->day());
        self::assertSame('vigil', $vigil->identity()->kind()->value());
        self::assertSame('violet', $vigil->colour()->base()->value());
        self::assertNotNull($vigil->vigilOfId());
        self::assertSame('roman:sanctorale:laurentius', $vigil->vigilOfId()->toString());
    }

    /**
     * The corpus reader reproduces every entry the retired seed carried, field for
     * field — the durable guard that the swap from inline data to generated data
     * (and any later corpus edit) never silently alters a verified entry.
     */
    public function testReproducesEverySeedEntryIdentically(): void
    {
        $corpus = $this->byId();

        foreach ((new SeedSanctoralData())->entries() as $seed) {
            $id = $seed->identity()->id()->toString();
            $actual = $corpus[$id] ?? null;
            self::assertInstanceOf(SanctoralEntry::class, $actual, "corpus is missing seed entry {$id}");

            self::assertSame($seed->month(), $actual->month(), "month for {$id}");
            self::assertSame($seed->day(), $actual->day(), "day for {$id}");
            self::assertSame(
                $seed->identity()->kind()->value(),
                $actual->identity()->kind()->value(),
                "kind for {$id}"
            );
            self::assertSame($seed->rank()->ordinal(), $actual->rank()->ordinal(), "rank for {$id}");
            self::assertSame($seed->colour()->base()->value(), $actual->colour()->base()->value(), "colour for {$id}");
            self::assertSame($seed->colour()->roseAllowed(), $actual->colour()->roseAllowed(), "rose for {$id}");
            self::assertSame($seed->identity()->names(), $actual->identity()->names(), "names for {$id}");
            self::assertSame($seed->identity()->titulars(), $actual->identity()->titulars(), "titulars for {$id}");
            self::assertSame(
                $seed->vigilOfId() !== null ? $seed->vigilOfId()->toString() : null,
                $actual->vigilOfId() !== null ? $actual->vigilOfId()->toString() : null,
                "vigilOf for {$id}"
            );
        }
    }

    /**
     * @return array<string, SanctoralEntry>
     */
    private function byId(): array
    {
        $indexed = [];
        foreach ((new CorpusSanctoralData())->entries() as $entry) {
            $indexed[$entry->identity()->id()->toString()] = $entry;
        }

        return $indexed;
    }
}
