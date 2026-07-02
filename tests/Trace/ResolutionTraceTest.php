<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Trace;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function Introibo\Core\contract;
use function Introibo\Core\explain;

/**
 * The show-your-work resolution trace (#233/#234), exercised through the public
 * `explain()` / `contract()` entry points on real contested days.
 */
final class ResolutionTraceTest extends TestCase
{
    public function testDefaultContractLeavesResolutionNull(): void
    {
        self::assertNull(contract(new DateTimeImmutable('2024-06-20'))['resolution']);
        self::assertNull(contract(new DateTimeImmutable('2024-03-25'))['resolution']);
    }

    public function testExplainPopulatesTheTraceSlot(): void
    {
        $resolution = explain(new DateTimeImmutable('2024-06-20'))['resolution'];

        self::assertIsArray($resolution);
        foreach (['winner', 'candidates', 'losers', 'commemorationLimit'] as $key) {
            self::assertArrayHasKey($key, $resolution);
        }
    }

    public function testWinnerIsTheCelebrationAndCitesTheTableOfLiturgicalDays(): void
    {
        $day = explain(new DateTimeImmutable('2024-12-25'));
        $winner = $day['resolution']['winner'];

        self::assertSame($day['celebration'][0]['id'], $winner['id']);
        self::assertSame('n91-table-of-liturgical-days', $winner['rule']);
        self::assertSame('rg-1960:91', $winner['citation']);
        self::assertIsInt($winner['line']);
    }

    public function testCandidatesAreTheFieldWithTheWinnerFirst(): void
    {
        $resolution = explain(new DateTimeImmutable('2024-11-01'))['resolution'];

        self::assertNotSame([], $resolution['candidates']);
        self::assertSame($resolution['winner']['id'], $resolution['candidates'][0]['id']);
        // Every loser is one of the candidates the winner beat.
        self::assertSame(count($resolution['candidates']), count($resolution['losers']) + 1);
        foreach ($resolution['candidates'] as $candidate) {
            self::assertArrayHasKey('ordinal', $candidate['tier']);
            self::assertArrayHasKey('line', $candidate['tier']);
            self::assertArrayHasKey('selector', $candidate['tier']);
        }
    }

    /** A commemoration-grade saint yields to the feria and is commemorated (guards #424 in the trace). */
    public function testCommemorationGradeSaintIsCommemoratedNotCelebrated(): void
    {
        $resolution = explain(new DateTimeImmutable('2024-06-20'))['resolution'];

        self::assertStringContainsString('feria', $resolution['winner']['id']);
        $silverius = self::loser($resolution, 'roman:sanctorale:silverius');
        self::assertNotNull($silverius);
        self::assertSame('commemorate', $silverius['outcome']);
        self::assertSame('rg-1960:112', $silverius['citation']);
    }

    /** A first-class feast impeded into Holy Week is transferred, and the trace says why (n. 95). */
    public function testTransferredFeastShowsAsACitedTransferLoser(): void
    {
        $resolution = explain(new DateTimeImmutable('2024-03-25'))['resolution'];

        $annunciation = self::loser($resolution, 'roman:sanctorale:annuntiatio');
        self::assertNotNull($annunciation);
        self::assertSame('transfer', $annunciation['outcome']);
        self::assertSame('rg-1960:95', $annunciation['citation']);
    }

    public function testTraceExplainsTheColourOfTheCelebratedOffice(): void
    {
        $colour = explain(new DateTimeImmutable('2024-12-25'))['resolution']['colour'];

        self::assertSame('white', $colour['base']);
        self::assertFalse($colour['roseAllowed']);
        self::assertSame('colour-of-celebration', $colour['rule']);
        self::assertStringContainsString('colour of the celebrated office', $colour['summary']);
        self::assertSame('rg-1960', $colour['citation']);
    }

    public function testTraceExplainsTheSeasonFromTheTemporalOffice(): void
    {
        $season = explain(new DateTimeImmutable('2024-06-20'))['resolution']['season'];

        self::assertSame('pentecost', $season['value']);
        self::assertSame('season-of-temporal-office', $season['rule']);
        self::assertStringContainsString('season of the day', $season['summary']);
        self::assertSame('rg-1960', $season['citation']);
    }

    public function testEveryTraceStepCarriesAnRg1960Citation(): void
    {
        foreach (['2024-06-20', '2024-03-25', '2024-11-01', '2024-12-25', '2025-09-15'] as $date) {
            $resolution = explain(new DateTimeImmutable($date))['resolution'];

            self::assertMatchesRegularExpression('/^rg-1960:/', $resolution['winner']['citation'], $date);
            self::assertMatchesRegularExpression('/^rg-1960:/', $resolution['commemorationLimit']['citation'], $date);
            foreach ($resolution['losers'] as $loser) {
                $where = $date . ' ' . $loser['id'];
                self::assertMatchesRegularExpression('/^rg-1960:/', (string) $loser['citation'], $where);
                self::assertContains($loser['outcome'], ['commemorate', 'transfer', 'omit'], $date);
            }
        }
    }

    /**
     * @param array<string, mixed> $resolution
     *
     * @return array<string, mixed>|null
     */
    private static function loser(array $resolution, string $id): ?array
    {
        /** @var list<array<string, mixed>> $losers */
        $losers = $resolution['losers'];
        foreach ($losers as $loser) {
            if ($loser['id'] === $id) {
                return $loser;
            }
        }

        return null;
    }
}
