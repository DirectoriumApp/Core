<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use PHPUnit\Framework\TestCase;

/**
 * The two pre-1955 precedence tie-breaks of #453, resolved end-to-end:
 *
 *  * TWO SIMPLES on one day — the more worthy by the general Table of Precedence is celebrated and
 *    the other commemorated (Rubr. Gen., De occurrentia). 19 January carries two Simples in the
 *    universal calendar, Ss Marius, Martha, Audifax & Abachum (the older Roman martyrs) and St
 *    Canute; the former is the dignior. The engine models this with the `dignior-simple` tier, so
 *    the resolver's arbitrary id-string tie-break (which would wrongly prefer "canutus") never
 *    decides between two named Simples.
 *
 *  * GREATER DOUBLE vs an occurring/RESUMED Sunday — under the Divino Afflatu Sunday elevation a
 *    minor (per-annum) Sunday, resumed or not, OUTRANKS a greater double. On 18 November the
 *    Dedication of the Basilicas of Ss Peter & Paul (a Duplex maius) yields to the Sunday, which is
 *    celebrated with the Dedication commemorated. (Resumption changes the Sunday's TEXTS, not its
 *    rank — so this is unchanged by the resumed-Sunday machinery; a locking regression.)
 */
final class PrecedenceTieBreakResolutionTest extends TestCase
{
    private const MARIUS = 'roman:sanctorale:marius-et-socii';
    private const CANUTE = 'roman:sanctorale:canutus';
    private const HILARION = 'roman:sanctorale:hilarion';
    private const URSULA = 'roman:sanctorale:ursula-et-socii';
    private const DEDICATION = 'roman:sanctorale:dedicatio-basilicarum-petri-et-pauli';

    private static function resolve(string $urn, string $date): LiturgicalDay
    {
        return DayResolver::forEdition(RubricSystem::fromString($urn))
            ->resolveDay(new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    private static function celebrationId(LiturgicalDay $day): ?string
    {
        $celebration = $day->celebration();

        return $celebration === [] ? null : $celebration[0]->id()->toString();
    }

    private static function roleOf(LiturgicalDay $day, string $id): ?string
    {
        foreach ($day->offices() as $office) {
            if ($office->observance()->id()->toString() === $id) {
                return $office->role()->value();
            }
        }

        return null;
    }

    // --- 2a: two Simples on 19 January (a weekday, so no Saturday Office intervenes) ------

    public function testTheMoreWorthyOfTwoSimplesIsCelebratedTheOtherCommemorated(): void
    {
        // 19 Jan 1954 is a Tuesday: Ss Marius & Companions (the dignior Simple) is celebrated and
        // St Canute commemorated — NOT the id-string order, which alphabetically prefers "canutus".
        $day = self::resolve('roman:divino-afflatu', '1954-01-19');

        self::assertSame(self::MARIUS, self::celebrationId($day), 'The more worthy Simple is celebrated.');
        self::assertSame('commemoration', self::roleOf($day, self::CANUTE), 'The lesser Simple is commemorated.');
    }

    public function testTheSecondTwoSimplesDayResolvesByDignityNotAlphabet(): void
    {
        // 21 Oct 1954 is a Thursday carrying the calendar's other two-Simple collision: St Hilarion,
        // Abbot (the day's historic titular) is celebrated and Ss Ursula & Companions commemorated.
        // The id string happens to agree here ("hilarion" < "ursula-et-socii"); the `dignior-simple`
        // membership makes the outcome rule-driven, completing the universal fixed-date dignity set.
        $day = self::resolve('roman:divino-afflatu', '1954-10-21');

        self::assertSame(self::HILARION, self::celebrationId($day), 'The titular Simple is celebrated.');
        self::assertSame('commemoration', self::roleOf($day, self::URSULA), 'Ss Ursula & Co. are commemorated.');
    }

    // --- 2b: greater double yields to a minor / resumed Sunday (the Divino Afflatu elevation) --

    public function testAGreaterDoubleYieldsToAnOccurringSundayUnderTheDivinoAfflatuElevation(): void
    {
        // 18 Nov 1956 is a Sunday (a resumed Sunday after the Epiphany, since Easter 1956 was early).
        // A minor Sunday outranks a Duplex maius under Divino Afflatu, so the Sunday is celebrated and
        // the Dedication of Ss Peter & Paul is commemorated — the SAME outcome in 1954 and 1955.
        foreach (['roman:divino-afflatu', 'roman:rubricae-1955'] as $urn) {
            $day = self::resolve($urn, '1956-11-18');

            $celebration = self::celebrationId($day);
            self::assertNotNull($celebration);
            // The (resumed) Sunday is a temporal office; the Dedication is sanctoral. That the
            // temporal office takes the day proves the Sunday outranks the greater double.
            self::assertStringStartsWith(
                'roman:temporale:',
                (string) $celebration,
                "$urn: the (resumed) Sunday takes 18 Nov 1956, not the Dedication."
            );
            self::assertStringContainsString(
                'resumed-epiphany',
                (string) $celebration,
                "$urn: the celebrated office is the resumed Sunday after the Epiphany."
            );
            self::assertSame(
                'commemoration',
                self::roleOf($day, self::DEDICATION),
                "$urn: the greater-double Dedication is commemorated under the Sunday."
            );
        }
    }
}
