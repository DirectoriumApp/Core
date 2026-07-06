<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Overlay;

use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Overlay\CalendarOverlay;
use Directorium\Core\Overlay\OverlayConflict;
use Directorium\Core\Overlay\RerankOperation;
use Directorium\Core\Overlay\SuppressOperation;
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
        new CalendarOverlay('directorium:overlay:roman:test', 'Test', [
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
        $overlay = new CalendarOverlay('directorium:overlay:roman:sspx', 'SSPX', [
            new SuppressOperation(ObservanceId::parse('roman:sanctorale:laurentius')),
        ]);

        self::assertSame('directorium:overlay:roman:sspx', $overlay->id());
        self::assertSame('SSPX', $overlay->name());
        self::assertCount(1, $overlay->operations());
    }
}
