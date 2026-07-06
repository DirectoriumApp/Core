<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Calendar\CelebrationRole;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Calendar\RoledObservance;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Contract\Provenance;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Sanctoral\SanctoralObservance;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;

/**
 * Golden snapshots for the frozen output contract (v1.0.0): a simple day pins
 * the full shape, and the transfer/omit/commemoration cases pin the office-level
 * outcome and transfer links. The contract is deterministic, so the snapshots
 * are exact — any drift is a deliberate, reviewed change.
 */
final class DayContractTest extends TestCase
{
    /** The whole shape, pinned for an ordinary green feria (celebration is also the tempora). */
    public function testSimpleDaySerialisesToTheFrozenShape(): void
    {
        $expected = [
            'contractVersion' => '1.0.1',
            'corpusVersion' => Corpus::default()->corpusVersion(),
            'engineVersion' => '0.4.0',
            'rite' => 'roman',
            'edition' => 'roman:rubricae-1960',
            'date' => '2025-07-11',
            'season' => 'pentecost',
            'commemorationLimit' => 2,
            'celebration' => [self::pentecostFeria('celebration')],
            'commemoration' => [],
            'displaced' => [],
            'tempora' => [self::pentecostFeria('tempora')],
            'secondVespers' => [
                'outcome' => 'full-of-following',
                'favoursFollowing' => true,
                'holder' => null,
                'commemorated' => null,
            ],
            'firstVespers' => null,
            'resolution' => null,
            'fasting' => null,
            'calendar' => [
                'astronomical' => [
                    'goldenNumber' => 12,
                    'epact' => 0,
                    'solarCycle' => 18,
                    'dominicalLetter' => 'E',
                    'romanIndiction' => 3,
                    'lunarAge' => 14,
                ],
            ],
        ];

        self::assertSame($expected, contract(self::utc('2025-07-11')));
    }

    /** The three provenance axes are present, well-formed, and independent (corpus carries no edition token). */
    public function testProvenanceStampsThreeIndependentVersionAxes(): void
    {
        $day = contract(self::utc('2025-07-15'));

        self::assertSame('1.0.1', $day['contractVersion']);
        self::assertSame('0.4.0', $day['engineVersion']);
        self::assertSame('roman:rubricae-1960', $day['edition']);
        self::assertSame('roman', $day['rite']);

        $corpus = $day['corpusVersion'];
        self::assertIsString($corpus);
        self::assertSame(Corpus::default()->corpusVersion(), $corpus);
        // The corpus version names the data build only — never the edition.
        self::assertStringNotContainsString(':', $corpus);
        self::assertStringNotContainsString('roman', $corpus);
        self::assertStringNotContainsString('rubricae', $corpus);
    }

    /** A sanctoral celebration carries its names and titulars; the yielding Sunday is commemorated. */
    public function testSanctoralCelebrationCarriesIdentityAndCommemoration(): void
    {
        $day = contract(self::utc('2025-06-29'));

        self::assertSame(1, $day['commemorationLimit']);
        self::assertSame('pentecost', $day['season']);

        $celebration = $day['celebration'][0];
        self::assertSame('roman:sanctorale:petrus-paulus', $celebration['id']);
        self::assertSame('directorium:observance:roman:sanctorale:petrus-paulus', $celebration['urn']);
        self::assertSame('feast', $celebration['kind']);
        self::assertSame('I', $celebration['rank']);
        self::assertSame(1, $celebration['rankOrdinal']);
        self::assertNull($celebration['season']);
        self::assertSame(['la' => 'Ss. Petri et Pauli Apostolorum'], $celebration['names']);
        self::assertSame(['petrus', 'paulus'], $celebration['titulars']);
        self::assertNull($celebration['outcome']);

        $commemoration = $day['commemoration'][0];
        self::assertSame('roman:temporale:paschal:pentecost-time:sunday-3', $commemoration['id']);
        self::assertSame('sunday', $commemoration['kind']);
        self::assertSame('commemorate', $commemoration['outcome']);
    }

    /** A transferred feast links both ends: transferredTo on the impeded day, transferredFrom on the landing. */
    public function testTransferLinksBothEnds(): void
    {
        $impeded = contract(self::utc('2017-03-19'));
        $displaced = $impeded['displaced'][0];
        self::assertSame('roman:sanctorale:ioseph', $displaced['id']);
        self::assertSame('transfer', $displaced['outcome']);
        self::assertSame('2017-03-20', $displaced['transferredTo']);
        self::assertNull($displaced['transferredFrom']);

        $landing = contract(self::utc('2017-03-20'));
        $celebration = $landing['celebration'][0];
        self::assertSame('roman:sanctorale:ioseph', $celebration['id']);
        self::assertSame('2017-03-19', $celebration['transferredFrom']);
        self::assertNull($celebration['transferredTo']);
        self::assertNull($celebration['outcome']);
    }

    /** An ordinary feria under a feast is omitted, not commemorated — and says so. */
    public function testOmittedLoserIsMarkedOmit(): void
    {
        $day = contract(self::utc('2025-02-24'));

        self::assertSame('roman:sanctorale:matthias', $day['celebration'][0]['id']);
        self::assertSame([], $day['commemoration']);

        $displaced = $day['displaced'][0];
        self::assertSame('roman:temporale:paschal:sexagesima:feria-2', $displaced['id']);
        self::assertSame('omit', $displaced['outcome']);
    }

    /** Same inputs, byte-identical JSON — across two fully independent resolutions. */
    public function testSerialisationIsDeterministic(): void
    {
        self::assertSame(
            self::contractFor('2025-06-29')->toJson(),
            self::contractFor('2025-06-29')->toJson()
        );
    }

    /** toJson() is valid JSON that round-trips to the same array (JSON_THROW_ON_ERROR). */
    public function testToJsonRoundTripsToArray(): void
    {
        $contract = self::contractFor('2025-06-29');

        self::assertSame($contract->toArray(), json_decode($contract->toJson(), true));
    }

    /** The frozen flags leave slashes and Unicode unescaped in the serialised names. */
    public function testToJsonLeavesSlashesAndUnicodeUnescaped(): void
    {
        $office = new SanctoralObservance(
            new Observance(
                ObservanceId::parse('roman:sanctorale:test'),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                ['test'],
                ['la' => 'S. Tëst / Fictus']
            ),
            RankClass::classIII(),
            ElementColour::of(Colour::white())
        );
        $day = new LiturgicalDay(
            self::utc('2025-01-02'),
            [new RoledObservance($office, CelebrationRole::celebration())],
            [],
            [],
            []
        );

        $json = DayContract::from($day, new Provenance('roman:rubricae-1960', '1962-seed', '0.4.0'))->toJson();

        self::assertStringNotContainsString('\/', $json);
        self::assertStringContainsString('S. Tëst / Fictus', $json);
    }

    /**
     * The pentecost feria office as it appears in both the celebration and the
     * tempora role — identical but for the role, so both snapshots stay in step.
     *
     * @return array<string, mixed>
     */
    private static function pentecostFeria(string $role): array
    {
        return [
            'id' => 'roman:temporale:paschal:pentecost-time:week-4:feria-6',
            'urn' => 'directorium:observance:roman:temporale:paschal:pentecost-time:week-4:feria-6',
            'role' => $role,
            'kind' => 'feria',
            'rank' => 'IV',
            'rankOrdinal' => 4,
            'season' => 'pentecost',
            'colour' => [
                'base' => 'green',
                'roseAllowed' => false,
            ],
            'names' => ['la' => 'Feria VI infra Hebdomadam IV post Octavam Pentecostes'],
            'titulars' => [],
            'outcome' => null,
            'transferredTo' => null,
            'transferredFrom' => null,
            'vigilOf' => null,
            'octaveOf' => null,
            'aliases' => null,
            'citations' => null,
            'text' => null,
            'chant' => null,
            'audio' => null,
        ];
    }

    private static function contractFor(string $ymd): DayContract
    {
        $date = self::utc($ymd);
        $year = DayResolver::for1962()->resolveYear((int) $date->format('Y'));

        return DayContract::from($year->day($date), $year->provenance());
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
