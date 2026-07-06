<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Trace;

use Directorium\Core\Trace\ResolutionReason;
use PHPUnit\Framework\TestCase;

final class ResolutionReasonTest extends TestCase
{
    public function testCitedCarriesRuleSummaryAndCitation(): void
    {
        $reason = ResolutionReason::cited(
            'n95-first-class-transfer',
            'transferred: only a first-class feast moves',
            'rg-1960:95'
        );

        self::assertSame('n95-first-class-transfer', $reason->rule());
        self::assertSame('transferred: only a first-class feast moves', $reason->summary());
        self::assertSame('rg-1960:95', $reason->citationRef());
        self::assertNotNull($reason->citation());
        self::assertSame('rg-1960', $reason->citation()->sourceKey());
        self::assertSame('95', $reason->citation()->locator());
    }

    public function testUncitedLeavesTheCitationHonestlyAbsent(): void
    {
        $reason = ResolutionReason::uncited('some-structural-rule', 'no single rubric governs this');

        self::assertNull($reason->citation());
        self::assertNull($reason->citationRef());
    }

    public function testToArrayIsTheStepShape(): void
    {
        $reason = ResolutionReason::cited('commemoration-admitted', 'commemorated', 'rg-1960:112');

        self::assertSame(
            ['rule' => 'commemoration-admitted', 'summary' => 'commemorated', 'citation' => 'rg-1960:112'],
            $reason->toArray()
        );
    }

    public function testToArrayEmitsNullCitationWhenUncited(): void
    {
        self::assertSame(
            ['rule' => 'r', 'summary' => 's', 'citation' => null],
            ResolutionReason::uncited('r', 's')->toArray()
        );
    }
}
