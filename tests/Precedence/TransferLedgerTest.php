<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use DateTimeImmutable;
use DateTimeZone;
use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use Introibo\Core\Precedence\TransferLedger;
use Introibo\Core\Sanctoral\SanctoralObservance;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TransferLedgerTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $ledger = new TransferLedger();

        self::assertTrue($ledger->isEmpty());
        self::assertSame(0, $ledger->count());
    }

    public function testEnqueueTracksPendingFeasts(): void
    {
        $ledger = new TransferLedger();
        $ledger->enqueue(self::feast('ioseph'), self::utc('2025-03-23'));

        self::assertFalse($ledger->isEmpty());
        self::assertSame(1, $ledger->count());
    }

    public function testDequeueReturnsTheEarliestImpededFirst(): void
    {
        $ledger = new TransferLedger();
        $ledger->enqueue(self::feast('later'), self::utc('2025-04-01'));
        $ledger->enqueue(self::feast('earlier'), self::utc('2025-03-20'));

        self::assertSame('roman:sanctorale:earlier', $ledger->dequeue()->id()->toString());
        self::assertSame('roman:sanctorale:later', $ledger->dequeue()->id()->toString());
        self::assertTrue($ledger->isEmpty());
    }

    public function testDequeueBreaksSameDayTiesByCanonicalId(): void
    {
        $ledger = new TransferLedger();
        $ledger->enqueue(self::feast('zzz'), self::utc('2025-03-25'));
        $ledger->enqueue(self::feast('aaa'), self::utc('2025-03-25'));

        self::assertSame('roman:sanctorale:aaa', $ledger->dequeue()->id()->toString());
        self::assertSame('roman:sanctorale:zzz', $ledger->dequeue()->id()->toString());
    }

    public function testDequeueOnEmptyThrows(): void
    {
        $this->expectException(LogicException::class);

        (new TransferLedger())->dequeue();
    }

    private static function feast(string $subject): SanctoralObservance
    {
        return new SanctoralObservance(
            new Observance(
                ObservanceId::parse('roman:sanctorale:' . $subject),
                ObservanceKind::fromString(ObservanceKind::FEAST),
                [$subject],
                ['la' => 'Testis']
            ),
            RankClass::classI(),
            ElementColour::of(Colour::white())
        );
    }

    private static function utc(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
