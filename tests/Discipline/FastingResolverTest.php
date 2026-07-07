<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Discipline;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use PHPUnit\Framework\TestCase;

/**
 * The fast/abstinence resolution (#249) end to end, against the 1917 discipline
 * (`cic-1917`), cross-checked to canon 1252.
 *
 * Each row is a 2025 day whose obligation the 1917 Code fixes: complete abstinence on
 * every Friday (§1); fast and complete abstinence on Ash Wednesday, the Fridays and
 * Saturdays of Lent, the Ember days, and the retained vigils (§2); fast with partial
 * abstinence on the other weekdays of Lent (§3). Sundays and ordinary days carry none.
 * Resolution reads the day the 1962 engine produced, so the calendar drives it.
 */
final class FastingResolverTest extends TestCase
{
    /**
     * @return array{fast: bool, abstinence: string, reason: string, discipline: string, citation: string}|null
     */
    private static function fastingOn(string $date): ?array
    {
        $day = DayResolver::for1962()->resolveDay(
            new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'))
        );
        $obligation = $day->fasting();
        if ($obligation === null) {
            return null;
        }

        return [
            'fast' => $obligation->fast(),
            'abstinence' => $obligation->abstinence()->value(),
            'reason' => $obligation->reason(),
            'discipline' => $obligation->disciplineUrn(),
            'citation' => $obligation->citation(),
        ];
    }

    /**
     * @dataProvider obligedDays
     */
    public function testTheObligationMatchesCanon1252(
        string $date,
        bool $fast,
        string $abstinence,
        string $reason
    ): void {
        $obligation = self::fastingOn($date);

        self::assertNotNull($obligation, "Expected an obligation on $date.");
        self::assertSame($fast, $obligation['fast'], "fast on $date");
        self::assertSame($abstinence, $obligation['abstinence'], "abstinence on $date");
        self::assertSame($reason, $obligation['reason'], "reason on $date");
        self::assertSame('roman:cic-1917', $obligation['discipline'], "discipline on $date");
        self::assertSame('cic-1917:c1252', $obligation['citation'], "citation on $date");
    }

    /**
     * Days the 1917 discipline obliges in 2025 (Easter 20 April; Ash Wed 5 March;
     * Pentecost 8 June, its Ember days 11/13/14 June).
     *
     * @return array<string, array{string, bool, string, string}>
     */
    public function obligedDays(): array
    {
        return [
            'Ash Wednesday (5 Mar)' => ['2025-03-05', true, 'full', 'ash-wednesday'],
            'Friday of Lent (7 Mar)' => ['2025-03-07', true, 'full', 'lent-major'],
            'Saturday of Lent (8 Mar)' => ['2025-03-08', true, 'full', 'lent-major'],
            'Monday of Lent (10 Mar)' => ['2025-03-10', true, 'none', 'lent-minor'],
            'Ember Wednesday of Lent (12 Mar)' => ['2025-03-12', true, 'full', 'ember-day'],
            'Good Friday (18 Apr)' => ['2025-04-18', true, 'full', 'lent-major'],
            'Holy Saturday (19 Apr)' => ['2025-04-19', true, 'full', 'lent-major'],
            'Pentecost Ember Friday (13 Jun)' => ['2025-06-13', true, 'full', 'ember-day'],
            'ordinary Friday (18 Jul)' => ['2025-07-18', false, 'full', 'friday'],
            'Vigil of the Assumption (14 Aug)' => ['2025-08-14', true, 'full', 'vigil'],
            'Vigil of Christmas (24 Dec)' => ['2025-12-24', true, 'full', 'vigil'],
        ];
    }

    /**
     * @dataProvider freeDays
     */
    public function testDaysWithoutAnObligationCarryNone(string $date): void
    {
        self::assertNull(self::fastingOn($date), "Expected no obligation on $date.");
    }

    /**
     * @return array<string, array{string}>
     */
    public function freeDays(): array
    {
        return [
            'ordinary Tuesday' => ['2025-07-15'],
            'Sunday of Lent (exempt)' => ['2025-03-09'],
            'ordinary Thursday' => ['2025-07-17'],
            'Christmas Day (a feast, no fast)' => ['2025-12-25'],
        ];
    }

    /**
     * The vigil fast follows the calendar across editions: the All Saints vigil (31 Oct)
     * was suppressed by the 1960 rubrics but kept under Divino Afflatu, so the same
     * discipline data produces the fast under 1954 and nothing under 1962 — proving the
     * fasting-vigil id matches the real `omnes-sancti:vigilia` the corpus places. (31 Oct
     * 1950 is a Tuesday, so it is the celebrated vigil, not displaced by a Sunday.)
     */
    public function testTheAllSaintsVigilFastFollowsTheEditionThatKeepsIt(): void
    {
        $date = new DateTimeImmutable('1950-10-31 00:00:00', new DateTimeZone('UTC'));

        $under1954 = DayResolver::forEdition(RubricSystem::divinoAfflatu())->resolveDay($date)->fasting();
        self::assertNotNull($under1954, 'The All Saints vigil is a fast day under 1954.');
        self::assertTrue($under1954->fast());
        self::assertSame('full', $under1954->abstinence()->value());
        self::assertSame('vigil', $under1954->reason());

        // 1962 suppressed the vigil, so 31 Oct is an ordinary weekday — no vigil fast.
        $under1962 = DayResolver::forEdition(RubricSystem::rubricae1960())->resolveDay($date)->fasting();
        self::assertNull($under1962, 'The suppressed vigil leaves no fast under 1962.');
    }

    public function testTheStrictestAbstinenceIsKeptWhenSeveralRulesMeet(): void
    {
        // A Friday of Lent is both a Friday (full) and a Lenten major day (fast + full):
        // the fold keeps fast true and abstinence full, and names the Lenten rule.
        $obligation = self::fastingOn('2025-03-07');

        self::assertNotNull($obligation);
        self::assertTrue($obligation['fast']);
        self::assertSame('full', $obligation['abstinence']);
    }
}
