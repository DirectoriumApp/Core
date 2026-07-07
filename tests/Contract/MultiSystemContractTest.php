<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Edition\RubricSystem;
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
 * Cum nostra none (a first-class day's additional-commemoration count is zero). Since #453 flipped
 * their isBuilt flag, all three editions resolve through the public `contract()` boundary — the
 * same day is asked of each by naming its rubric system, no {@see DayResolver::forEdition()} needed.
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
     * The public boundary now serves the historical editions directly: naming 1954 or 1955 to
     * contract() resolves and stamps that edition, where before #453 it refused them. (A future
     * declared-but-unbuilt edition would still be refused; there is none to name today.)
     */
    public function testThePublicContractResolvesTheHistoricalEditionsDirectly(): void
    {
        $da = contract(self::utc('1958-08-15'), false, null, RubricSystem::DIVINO_AFFLATU);
        $cn = contract(self::utc('1958-08-15'), false, null, RubricSystem::RUBRICAE_1955);

        self::assertSame('roman:divino-afflatu', $da['edition']);
        self::assertSame('roman:rubricae-1955', $cn['edition']);
        self::assertSame('roman:sanctorale:assumptio', $da['celebration'][0]['id']);
        self::assertSame('roman:sanctorale:assumptio', $cn['celebration'][0]['id']);
    }

    /**
     * The Assumption's serialised day under the named rubric system, resolved through the public
     * contract() boundary — the same path a real caller uses to reach each built edition.
     *
     * @return array<string, mixed>
     */
    private static function assumptionUnder(string $selector): array
    {
        return contract(self::utc('1958-08-15'), false, null, $selector);
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
