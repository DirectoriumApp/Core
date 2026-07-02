<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Observance\ObservanceId;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\contract;

/**
 * The published `id`/`urn` are the stable cross-system compatibility surface
 * (Epic #52, #55). This guards a representative set of identifiers — a temporal
 * Sunday and feria, a sanctoral feast, and a sanctoral vigil — against
 * accidental change, and confirms the urn is the id under the platform URN
 * scheme, round-tripping through {@see ObservanceId::parse}.
 */
final class StableIdentifierTest extends TestCase
{
    private const URN_PREFIX = 'introibo:observance:';

    /**
     * @dataProvider stableIdentifiers
     */
    public function testPublishedIdentifierIsPinnedAndRoundTrips(string $ymd, string $role, string $expectedId): void
    {
        $office = self::firstOffice($ymd, $role);

        // The id is the stable cross-system feast id: identity only, no date/rank.
        self::assertSame($expectedId, $office['id']);

        // The urn is that same id under the platform URN scheme — the mapping
        // from observance id to stable feast id is the identity function.
        self::assertSame(self::URN_PREFIX . $expectedId, $office['urn']);

        $urn = $office['urn'];
        self::assertIsString($urn);
        $idFromUrn = substr($urn, strlen(self::URN_PREFIX));
        self::assertSame($expectedId, ObservanceId::parse($idFromUrn)->toString());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function stableIdentifiers(): array
    {
        return [
            'temporal Sunday' => [
                '2025-06-29',
                'tempora',
                'roman:temporale:paschal:pentecost-time:sunday-3',
            ],
            'temporal feria' => [
                '2025-07-11',
                'celebration',
                'roman:temporale:paschal:pentecost-time:week-4:feria-6',
            ],
            'sanctoral feast' => [
                '2025-06-29',
                'celebration',
                'roman:sanctorale:petrus-paulus',
            ],
            'sanctoral vigil' => [
                '2025-08-14',
                'celebration',
                'roman:sanctorale:assumptio:vigilia',
            ],
        ];
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
