<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Overlay;

use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Overlay\CalendarOverlay;
use Introibo\Core\Overlay\OverlayConflict;
use Introibo\Core\Overlay\RerankOperation;
use Introibo\Core\Overlay\SuppressOperation;
use PHPUnit\Framework\TestCase;

/**
 * The overlay value object (#76): it rejects an anonymous or self-conflicting
 * overlay at construction so application never has to resolve an ambiguity.
 */
final class CalendarOverlayTest extends TestCase
{
    public function testRejectsTwoOperationsOnTheSameFeast(): void
    {
        $target = ObservanceId::parse('roman:sanctorale:laurentius');

        $this->expectException(OverlayConflict::class);
        new CalendarOverlay('introibo:overlay:roman:test', 'Test', [
            new RerankOperation($target, RankClass::classI()),
            new SuppressOperation($target),
        ]);
    }

    public function testRejectsAnEmptyId(): void
    {
        $this->expectException(OverlayConflict::class);
        new CalendarOverlay('', 'Nameless', []);
    }

    public function testKeepsItsIdNameAndOperations(): void
    {
        $overlay = new CalendarOverlay('introibo:overlay:roman:sspx', 'SSPX', [
            new SuppressOperation(ObservanceId::parse('roman:sanctorale:laurentius')),
        ]);

        self::assertSame('introibo:overlay:roman:sspx', $overlay->id());
        self::assertSame('SSPX', $overlay->name());
        self::assertCount(1, $overlay->operations());
    }
}
