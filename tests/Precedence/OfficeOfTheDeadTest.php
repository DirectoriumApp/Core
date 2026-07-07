<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Precedence\DayResolver;
use PHPUnit\Framework\TestCase;

/**
 * The Commemoration of All Souls (2 November) — the Office of the Dead (#453).
 *
 * All Souls is kept in EVERY edition: black, a first-class day under the 1960 Codex and a
 * Duplex under the pre-1955 Divino Afflatu rubrics, mapped to the reserved `all-souls`
 * occurrence tier by its {@see ObservanceKind::OFFICE_OF_THE_DEAD} kind. The one rule beyond
 * the Table of Liturgical Days is that a requiem is never sung on a Sunday: when 2 November is
 * a Sunday the Office and Mass yield to the Sunday and are transferred to 3 November.
 *
 * Implementing it retired the pre-existing All-Saints duplicate `omnium-sanctorum`, whose
 * phantom transfer onto 2 November had masked the missing Office of the Dead. These outcomes
 * agree with the missalemeum (1962) and SSPX oracles day-by-day; see the validation harness.
 */
final class OfficeOfTheDeadTest extends TestCase
{
    private const ALL_SOULS = 'roman:sanctorale:omnium-fidelium-defunctorum';
    private const ALL_SAINTS = 'roman:sanctorale:omnes-sancti';
    private const DUPLICATE = 'roman:sanctorale:omnium-sanctorum';

    /**
     * @return array<string, mixed> the day's output contract
     */
    private static function contract(string $edition, string $date): array
    {
        $resolver = DayResolver::forEdition(RubricSystem::fromString($edition));
        $resolved = $resolver->resolveYear((int) substr($date, 0, 4));
        $day = $resolved->day(new DateTimeImmutable($date, new DateTimeZone('UTC')));

        return DayContract::from($day, $resolver->provenance())->toArray();
    }

    /**
     * @param array<string, mixed> $contract
     *
     * @return array<string, mixed>|null
     */
    private static function celebration(array $contract): ?array
    {
        return $contract['celebration'][0] ?? null;
    }

    /**
     * @param array<string, mixed> $contract
     *
     * @return list<string> the ids of every office on the day (any role)
     */
    private static function officeIds(array $contract): array
    {
        $ids = [];
        foreach (['celebration', 'commemoration', 'displaced', 'tempora'] as $role) {
            foreach ($contract[$role] ?? [] as $office) {
                $ids[] = $office['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $contract
     *
     * @return list<string> the ids of every office displaced by transfer
     */
    private static function transferredIds(array $contract): array
    {
        $ids = [];
        foreach ($contract['displaced'] ?? [] as $office) {
            if (($office['outcome'] ?? null) === 'transfer') {
                $ids[] = $office['id'];
            }
        }

        return $ids;
    }

    /**
     * On an ordinary weekday, All Souls is the day: black, class I under 1962, with no
     * commemoration. (2 November 2024 is a Saturday.)
     */
    public function testAllSoulsIsCelebratedOnNovemberSecondUnder1962(): void
    {
        $day = self::contract('1962', '2024-11-02');
        $celebration = self::celebration($day);

        self::assertNotNull($celebration);
        self::assertSame(self::ALL_SOULS, $celebration['id']);
        self::assertSame(ObservanceKind::OFFICE_OF_THE_DEAD, $celebration['kind']);
        self::assertSame(1, $celebration['rankOrdinal']);
        self::assertSame('black', $celebration['colour']['base']);
        self::assertCount(0, $day['commemoration']);
    }

    /**
     * The pre-1955 edition keeps All Souls too — black, a Duplex (class III by the normalised
     * scheme). (2 November 1954 is a Tuesday, within the edition's validity window.)
     */
    public function testAllSoulsIsCelebratedOnNovemberSecondUnder1954(): void
    {
        $day = self::contract('1954', '1954-11-02');
        $celebration = self::celebration($day);

        self::assertNotNull($celebration);
        self::assertSame(self::ALL_SOULS, $celebration['id']);
        self::assertSame(ObservanceKind::OFFICE_OF_THE_DEAD, $celebration['kind']);
        self::assertSame(3, $celebration['rankOrdinal'], 'A pre-1955 Duplex normalises to class III.');
        self::assertSame('black', $celebration['colour']['base']);
    }

    /**
     * A requiem is never sung on a Sunday: when 2 November is a Sunday the Sunday is
     * celebrated and All Souls is transferred to 3 November. Proven in both editions
     * (2 November is a Sunday in 2025 and, within the 1954 window, in 1952).
     *
     * @dataProvider sundayCases
     */
    public function testAllSoulsYieldsToASundayAndIsTransferredToNovemberThird(
        string $edition,
        string $novemberSecond,
        string $novemberThird
    ): void {
        $sunday = self::contract($edition, $novemberSecond);
        $celebration = self::celebration($sunday);

        self::assertNotNull($celebration);
        self::assertSame(ObservanceKind::SUNDAY, $celebration['kind'], 'The Sunday holds 2 November.');
        self::assertNotSame(self::ALL_SOULS, $celebration['id']);
        self::assertContains(
            self::ALL_SOULS,
            self::transferredIds($sunday),
            'All Souls is transferred off the Sunday.'
        );

        $monday = self::contract($edition, $novemberThird);
        self::assertSame(self::ALL_SOULS, self::celebration($monday)['id'], 'All Souls lands on 3 November.');
        self::assertSame('black', self::celebration($monday)['colour']['base']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function sundayCases(): array
    {
        return [
            '1962 (2 Nov 2025 is a Sunday)' => ['1962', '2025-11-02', '2025-11-03'],
            '1954 (2 Nov 1952 is a Sunday)' => ['1954', '1952-11-02', '1952-11-03'],
        ];
    }

    /**
     * The pre-existing All-Saints duplicate is gone: 1 November carries All Saints and never
     * the retired `omnium-sanctorum`, in every edition.
     *
     * @dataProvider editions
     */
    public function testAllSaintsHasNoDuplicateOnNovemberFirst(string $edition): void
    {
        $day = self::contract($edition, '2024-11-01');
        $ids = self::officeIds($day);

        self::assertContains(self::ALL_SAINTS, $ids);
        self::assertNotContains(self::DUPLICATE, $ids);
    }

    /**
     * All Souls is present in every built and unbuilt edition on 2 November.
     *
     * @dataProvider editions
     */
    public function testAllSoulsIsKeptInEveryEdition(string $edition): void
    {
        $day = self::contract($edition, '2024-11-02');

        self::assertSame(self::ALL_SOULS, self::celebration($day)['id']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function editions(): array
    {
        return [
            'edition 1962' => ['1962'],
            'edition 1954' => ['1954'],
            'edition 1955' => ['1955'],
        ];
    }
}
