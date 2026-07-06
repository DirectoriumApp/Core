<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Trace;

use PHPUnit\Framework\TestCase;

/**
 * The golden-trace regression gate (#240): the live resolution trace of every
 * contested day must equal its frozen master, so a change to the engine's reasoning
 * cannot slip through unreviewed. See {@see GoldenTrace}.
 */
final class GoldenTraceTest extends TestCase
{
    public function testEveryContestedDayMatchesItsGoldenTrace(): void
    {
        $frozen = @file_get_contents(GoldenTrace::fixturePath());
        self::assertIsString($frozen, 'golden-traces fixture missing; run bin/freeze-golden-traces.php');

        $liveLines = self::lines(GoldenTrace::freezeText());
        $frozenLines = self::lines($frozen);

        self::assertSame(
            count($frozenLines),
            count($liveLines),
            'The set of contested days changed; run bin/freeze-golden-traces.php.'
        );

        foreach ($liveLines as $i => $line) {
            /** @var array{date: string} $record */
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(
                $frozenLines[$i],
                $line,
                sprintf('Resolution trace drift on %s; if reviewed, run bin/freeze-golden-traces.php.', $record['date'])
            );
        }
    }

    public function testFixtureCoversExactlyTheContestedDays(): void
    {
        $frozen = self::lines((string) file_get_contents(GoldenTrace::fixturePath()));

        $dates = array_map(
            static function (string $line): string {
                /** @var array{date: string} $record */
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                return $record['date'];
            },
            $frozen
        );

        self::assertSame(array_keys(GoldenTrace::CONTESTED), $dates);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return array_values(array_filter(explode("\n", trim($text))));
    }
}
