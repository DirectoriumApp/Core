<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;

/**
 * A provisional, representative seed of the 1962 General Roman Calendar's
 * fixed-date sanctoral entries.
 *
 * This is NOT the complete calendar. It is a deliberately small, cited,
 * 100%-correct slice chosen to exercise every overlay mechanism: each rank
 * class (I–IV), a feast in the surviving Christmas octave, the bissextile
 * candidates (St Matthias 24 Feb, St Gabriel 27 Feb), and — added in #27 — the
 * surviving vigils. The full, cited General Calendar is authored by the corpus
 * epic (#38); when it lands it provides another {@see SanctoralData}
 * implementation and this seed is retired. See
 * docs/design/sanctoral-overlay-model.md for the seed rationale and sources.
 */
final class SeedSanctoralData implements SanctoralData
{
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
     * Build one fixed-date entry. Rank is given as its ordinal (1 = class I,
     * highest) and colour by its machine name; both are widened to their value
     * objects here to keep the table above readable. The kind defaults to a
     * feast; a saint suppressed under the 1960 reform and kept only as a
     * commemoration passes {@see ObservanceKind::COMMEMORATION_ONLY} (there is
     * no IV-class saints' *feast* in 1962 — IV models the commemoration tier).
     *
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
     * Build one surviving sanctoral vigil, kept on the day before its feast and
     * carrying the feast's id as its parent link. Vigils are violet under the
     * 1960 rubrics.
     *
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
