<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;
use function Directorium\Core\explain;

/**
 * The frozen 1.0 output-contract shape (#90). These pins are the *structural* freeze —
 * the exact key layout of the contract, independent of the values — so a field added,
 * removed, renamed, or reordered fails here with a clear message. Value-level stability
 * across the centuries is the separate, exhaustive job of the golden-year digest.
 *
 * Adding a field is a minor contract change (docs/design/output-contract.md), but it
 * must be a *conscious* one: it fails this test until the expected shape below is
 * updated in the same, reviewed change.
 */
final class ContractShapeTest extends TestCase
{
    private const CONTRACT_VERSION = '1.0.2';

    /** The reserved top-level keys, in order. Every one is always present. */
    private const TOP_LEVEL = [
        'contractVersion', 'corpusVersion', 'engineVersion', 'rite', 'edition', 'date',
        'season', 'commemorationLimit', 'celebration', 'commemoration', 'displaced',
        'tempora', 'secondVespers', 'firstVespers', 'resolution', 'fasting', 'calendar',
    ];

    /** Every office (celebration/commemoration/…) carries this fixed key set. */
    private const OFFICE = [
        'id', 'urn', 'role', 'kind', 'rank', 'rankOrdinal', 'season', 'colour', 'names',
        'titulars', 'outcome', 'transferredTo', 'transferredFrom', 'vigilOf', 'octaveOf',
        'aliases', 'citations', 'text', 'chant', 'audio',
    ];

    public function testTopLevelShapeAndVersionAreFrozen(): void
    {
        $contract = contract(new DateTimeImmutable('2026-06-30'));

        self::assertSame(self::CONTRACT_VERSION, $contract['contractVersion']);
        self::assertSame(self::TOP_LEVEL, array_keys($contract), 'the frozen top-level contract shape changed');
    }

    public function testOfficeShapeIsFrozen(): void
    {
        $contract = contract(new DateTimeImmutable('2026-06-30'));

        self::assertNotSame([], $contract['celebration']);
        self::assertSame(self::OFFICE, array_keys($contract['celebration'][0]), 'the frozen office shape changed');
    }

    public function testCalendarBlockShapeIsFrozen(): void
    {
        // Universal: only the astronomical sub-block (date-only numbers).
        $universal = contract(new DateTimeImmutable('2026-06-30'));
        self::assertSame(['astronomical'], array_keys($universal['calendar']));
        self::assertSame(
            ['goldenNumber', 'epact', 'solarCycle', 'dominicalLetter', 'romanIndiction', 'lunarAge'],
            array_keys($universal['calendar']['astronomical'])
        );
        self::assertArrayNotHasKey('particular', $universal['calendar']);

        // Under an overlay the particular descriptor appears alongside astronomical.
        $overlay = contract(new DateTimeImmutable('2026-09-03'), false, 'sspx');
        self::assertSame(['particular', 'astronomical'], array_keys($overlay['calendar']));
        self::assertSame(['id', 'name'], array_keys($overlay['calendar']['particular']));
    }

    public function testResolutionSlotShapeIsFrozen(): void
    {
        // Null unless explaining; a fixed structure when filled.
        self::assertNull(contract(new DateTimeImmutable('2026-11-02'))['resolution']);

        $explained = explain(new DateTimeImmutable('2026-11-02'));
        self::assertSame(
            ['winner', 'candidates', 'losers', 'commemorationLimit', 'colour', 'season'],
            array_keys($explained['resolution'])
        );
    }
}
