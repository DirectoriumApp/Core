<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;

/**
 * The output contract across the three rubric systems (#72 regression safety): the `edition`
 * field stamps the active system (#74) and `commemorationLimit` reports that system's
 * per-edition class cap (#332). Both are proved on the SAME day resolved under each edition,
 * so a single feast shows all three answers side by side.
 *
 * The Assumption (15 Aug) is a first-class feast under every edition, so its class-I
 * commemoration cap is exactly the reform story: 1962 admits one, the pre-1955 rite three, and
 * Cum nostra none (a first-class day's additional-commemoration count is zero). The public
 * `contract()` boundary resolves only the built default (1962); 1954 and 1955 are reached the
 * same way the validation harness reaches them, through {@see DayResolver::forEdition()}.
 */
final class MultiSystemContractTest extends TestCase
{
    /**
     * @dataProvider systems
     */
    public function testTheContractStampsTheActiveSystemAndItsCommemorationLimit(
        string $selector,
        string $expectedEdition,
        int $expectedClassLimit
    ): void {
        $day = self::assumptionUnder($selector);

        self::assertSame($expectedEdition, $day['edition'], 'edition stamps the active rubric system');
        self::assertSame('roman', $day['rite'], 'rite is the edition head');
        self::assertSame(
            'roman:sanctorale:assumptio',
            $day['celebration'][0]['id'],
            'the Assumption is the celebrated office under every edition'
        );
        self::assertSame(
            $expectedClassLimit,
            $day['commemorationLimit'],
            "$expectedEdition reports its own class cap for a first-class day"
        );
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public function systems(): array
    {
        return [
            // selector => [selector, expected edition URN, class-I commemoration cap]
            '1962 (Rubricae 1960)' => [RubricSystem::RUBRICAE_1960, 'roman:rubricae-1960', 1],
            '1954 (Divino Afflatu)' => [RubricSystem::DIVINO_AFFLATU, 'roman:divino-afflatu', 3],
            '1955 (Cum nostra)' => [RubricSystem::RUBRICAE_1955, 'roman:rubricae-1955', 0],
        ];
    }

    /**
     * The three edition stamps are distinct — the field is a real discriminator, not a
     * constant — and each is the URN of the system that resolved the day.
     */
    public function testEachEditionStampIsDistinct(): void
    {
        $editions = [];
        foreach (array_keys($this->systems()) as $label) {
            [$selector, $expected] = $this->systems()[$label];
            $editions[$this->assumptionUnder($selector)['edition']] = true;
            self::assertArrayHasKey($expected, $editions);
        }

        self::assertCount(3, $editions, 'each rubric system stamps a distinct edition URN');
    }

    /** With no rubric system named, the public contract resolves — and stamps — 1962. */
    public function testThePublicContractDefaultsSafelyToNineteenSixtyTwo(): void
    {
        $day = contract(self::utc('1958-08-15'));

        self::assertSame('roman:rubricae-1960', $day['edition']);
        self::assertSame(1, $day['commemorationLimit']);
    }

    /**
     * The public boundary refuses a declared-but-unbuilt edition (1954/1955 are stamped only
     * through the resolver, not day()/contract()); this documents the gate the cross-system
     * assertions deliberately bypass.
     */
    public function testThePublicContractRefusesAnUnbuiltEdition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        contract(self::utc('1958-08-15'), false, null, RubricSystem::DIVINO_AFFLATU);
    }

    /**
     * The Assumption's serialised day under the named rubric system, built the way the
     * validation harness resolves the historical editions.
     *
     * @return array<string, mixed>
     */
    private static function assumptionUnder(string $selector): array
    {
        $date = self::utc('1958-08-15');
        $year = DayResolver::forEdition(RubricSystem::fromString($selector))
            ->resolveYear((int) $date->format('Y'));

        return DayContract::from($year->day($date), $year->provenance())->toArray();
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
