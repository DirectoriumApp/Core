<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;

/**
 * The office's human-readable text is i18n-shaped — a locale-keyed `names` map
 * with the invariant `la` always present and no vernacular baked in v1.0 — and
 * the proper-text pipelines have reserved, nullable hooks (`text`, `chant`,
 * `audio`, `citations`) to fill later without reshaping the contract (Epic #52,
 * #56).
 */
final class OfficeI18nTest extends TestCase
{
    /**
     * @dataProvider offices
     */
    public function testNamesAreI18nKeyedWithLatinAlwaysPresentAndNoBakedVernacular(
        string $ymd,
        string $role
    ): void {
        $names = self::firstOffice($ymd, $role)['names'];

        self::assertIsArray($names);
        self::assertArrayHasKey('la', $names);
        self::assertNotSame('', $names['la']);
        // v1.0 ships the invariant Latin only — no baked vernacular translations.
        self::assertSame(['la'], array_keys($names));
    }

    /**
     * @dataProvider offices
     */
    public function testContentHooksArePresentAndNull(string $ymd, string $role): void
    {
        $office = self::firstOffice($ymd, $role);

        foreach (['text', 'chant', 'audio', 'citations'] as $hook) {
            self::assertArrayHasKey($hook, $office);
            self::assertNull($office[$hook]);
        }
    }

    public function testTitularsArePresentForSanctoralAndEmptyForTemporal(): void
    {
        self::assertSame(['petrus', 'paulus'], self::firstOffice('2025-06-29', 'celebration')['titulars']);
        self::assertSame([], self::firstOffice('2025-07-11', 'celebration')['titulars']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function offices(): array
    {
        return [
            'sanctoral feast' => ['2025-06-29', 'celebration'],
            'temporal feria' => ['2025-07-11', 'celebration'],
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
