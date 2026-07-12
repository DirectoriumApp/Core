<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

use function Directorium\Core\contract;
use function Directorium\Core\explain;

/**
 * The 1.x output-contract shape (#90). These pins are the *structural* freeze — the exact
 * key layout of the contract, independent of the values — so a field added, removed,
 * renamed, or reordered fails here with a clear message. Value-level stability across the
 * centuries is the separate, exhaustive job of the golden-year digest.
 *
 * Adding a field is a minor contract change (docs/design/output-contract.md), but it
 * must be a *conscious* one: it fails this test until the expected shape below is
 * updated in the same, reviewed change. (`optionalMemorials` joined at 1.1.0, #260.)
 */
final class ContractShapeTest extends TestCase
{
    private const CONTRACT_VERSION = '1.1.0';

    /** The reserved top-level keys, in order. Every one is always present. */
    private const TOP_LEVEL = [
        'contractVersion', 'corpusVersion', 'engineVersion', 'rite', 'edition', 'date',
        'season', 'commemorationLimit', 'celebration', 'commemoration', 'displaced',
        'tempora', 'optionalMemorials', 'secondVespers', 'firstVespers', 'resolution', 'fasting', 'calendar',
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

    public function testOptionalMemorialsShapeIsFrozen(): void
    {
        // Empty on every traditional day — the 1962 golden's values are unmoved by the slot.
        self::assertSame([], contract(new DateTimeImmutable('2026-06-30'))['optionalMemorials']);

        // A Novus-Ordo Ordinary-Time feria offers its electable optional memorials (Fabian and
        // Sebastian on 20 Jan): each is a self-describing office of the fixed shape, with no
        // occurrence role (they are offered, not resolved).
        $no = contract(new DateTimeImmutable('2025-01-20'), false, null, 'roman:novus-ordo-2002');
        self::assertNotSame([], $no['optionalMemorials'], 'the NO feria should offer optional memorials');
        self::assertSame(self::OFFICE, array_keys($no['optionalMemorials'][0]), 'the optional-memorial shape changed');
        self::assertNull($no['optionalMemorials'][0]['role'], 'an offered office carries no occurrence role');
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
