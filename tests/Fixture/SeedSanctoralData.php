<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Fixture;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\SanctoralData;
use Directorium\Core\Sanctoral\SanctoralEntry;

/**
 * A small, representative, 100%-correct slice of the 1962 sanctoral — the test
 * fixture the engine was proven against before the cited corpus existed.
 *
 * It was the engine's provisional {@see SanctoralData} through the overlay,
 * precedence, and contract epics; the cited {@see \Directorium\Core\Sanctoral\CorpusSanctoralData}
 * (issue #41) is now the production source, and this seed is retained here as a
 * controlled fixture. Its 21 entries are the verified anchors the corpus must
 * reproduce byte for byte (see the CorpusSanctoralData test), and they exercise
 * every overlay mechanism: each rank class (I–IV), the bissextile candidates
 * (St Matthias 24 Feb, St Gabriel 27 Feb), the surviving Christmas-octave feasts,
 * and the surviving vigils. Tests that need a known, sparse calendar inject this
 * fixture rather than the full production corpus.
 */
final class SeedSanctoralData implements SanctoralData
{
    /**
     * The version stamp for this seed corpus, dated to when the slice was last
     * revised. It names only the data build — no edition token — so the same
     * seed can be resolved under any edition.
     */
    public function version(): string
    {
        return '1962-seed-2026-07-02';
    }

    /** @return list<SanctoralEntry> */
    public function entries(): array
    {
        return [
            $this->entry(2, 22, 'cathedra-petri', 2, 'white', 'Cathedra S. Petri Apostoli', ['petrus']),
            $this->entry(2, 24, 'matthias', 2, 'red', 'S. Matthiae Apostoli', ['matthias']),
            $this->entry(
                2,
                27,
                'gabriel-a-virgine-perdolente',
                3,
                'white',
                'S. Gabrielis a Virgine Perdolente Confessoris',
                ['gabriel-a-virgine-perdolente']
            ),
            $this->entry(
                3,
                7,
                'thomas-aquinas',
                3,
                'white',
                'S. Thomae de Aquino Confessoris et Ecclesiae Doctoris',
                ['thomas-aquinas']
            ),
            $this->entry(3, 19, 'ioseph', 1, 'white', 'S. Ioseph Sponsi B.M.V. Confessoris', ['ioseph']),
            $this->entry(3, 25, 'annuntiatio', 1, 'white', 'In Annuntiatione B.M.V.', ['maria']),
            $this->entry(
                6,
                24,
                'nativitas-ioannis-baptistae',
                1,
                'white',
                'In Nativitate S. Ioannis Baptistae',
                ['ioannes-baptista']
            ),
            $this->entry(6, 29, 'petrus-paulus', 1, 'red', 'Ss. Petri et Pauli Apostolorum', ['petrus', 'paulus']),
            $this->entry(
                7,
                10,
                'septem-fratres',
                3,
                'red',
                'Ss. Septem Fratrum Martyrum ac Rufinae et Secundae Virginum et Martyrum',
                ['septem-fratres']
            ),
            $this->entry(8, 15, 'assumptio', 1, 'white', 'In Assumptione B.M.V.', ['maria']),
            $this->entry(11, 1, 'omnes-sancti', 1, 'white', 'Festum Omnium Sanctorum', ['omnes-sancti']),
            $this->entry(
                11,
                8,
                'quatuor-coronati',
                4,
                'red',
                'Ss. Quatuor Coronatorum Martyrum',
                ['quatuor-coronati'],
                ObservanceKind::COMMEMORATION_ONLY
            ),
            $this->entry(12, 8, 'immaculata-conceptio', 1, 'white', 'In Conceptione Immaculata B.M.V.', ['maria']),
            $this->entry(12, 26, 'stephanus', 2, 'red', 'S. Stephani Protomartyris', ['stephanus']),
            $this->entry(
                12,
                27,
                'ioannes-evangelista',
                2,
                'white',
                'S. Ioannis Apostoli et Evangelistae',
                ['ioannes-evangelista']
            ),
            $this->entry(12, 28, 'innocentes', 2, 'red', 'Ss. Innocentium Martyrum', ['innocentes']),
            $this->entry(8, 10, 'laurentius', 2, 'red', 'S. Laurentii Martyris', ['laurentius']),
            $this->vigil(
                6,
                23,
                'ioannes-baptista:vigilia',
                2,
                'In Vigilia S. Ioannis Baptistae',
                ['ioannes-baptista'],
                'nativitas-ioannis-baptistae'
            ),
            $this->vigil(
                6,
                28,
                'petrus-paulus:vigilia',
                2,
                'In Vigilia Ss. Petri et Pauli Apostolorum',
                ['petrus', 'paulus'],
                'petrus-paulus'
            ),
            $this->vigil(
                8,
                9,
                'laurentius:vigilia',
                3,
                'In Vigilia S. Laurentii Martyris',
                ['laurentius'],
                'laurentius'
            ),
            $this->vigil(
                8,
                14,
                'assumptio:vigilia',
                2,
                'In Vigilia Assumptionis B.M.V.',
                ['maria'],
                'assumptio'
            ),
        ];
    }

    /**
     * @param list<string> $titulars
     */
    private function entry(
        int $month,
        int $day,
        string $slug,
        int $rankOrdinal,
        string $colour,
        string $latinName,
        array $titulars,
        string $kind = ObservanceKind::FEAST
    ): SanctoralEntry {
        $identity = new Observance(
            ObservanceId::parse('roman:sanctorale:' . $slug),
            ObservanceKind::fromString($kind),
            $titulars,
            ['la' => $latinName]
        );

        return new SanctoralEntry(
            $month,
            $day,
            $identity,
            RankClass::fromOrdinal($rankOrdinal),
            ElementColour::of(Colour::fromString($colour))
        );
    }

    /**
     * @param list<string> $titulars
     */
    private function vigil(
        int $month,
        int $day,
        string $slug,
        int $rankOrdinal,
        string $latinName,
        array $titulars,
        string $ofSlug
    ): SanctoralEntry {
        $identity = new Observance(
            ObservanceId::parse('roman:sanctorale:' . $slug),
            ObservanceKind::fromString(ObservanceKind::VIGIL),
            $titulars,
            ['la' => $latinName]
        );

        return new SanctoralEntry(
            $month,
            $day,
            $identity,
            RankClass::fromOrdinal($rankOrdinal),
            ElementColour::of(Colour::violet()),
            ObservanceId::parse('roman:sanctorale:' . $ofSlug)
        );
    }
}
