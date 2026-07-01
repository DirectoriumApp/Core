<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Observance;

use Introibo\Core\Observance\AnchorFamily;
use Introibo\Core\Observance\ObservanceId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ObservanceIdTest extends TestCase
{
    /**
     * @dataProvider validSlugs
     */
    public function testParsesAndRoundTripsValidSlugs(string $slug): void
    {
        $id = ObservanceId::parse($slug);

        self::assertSame($slug, $id->toString());
        self::assertSame($slug, (string) $id);
        self::assertTrue($id->equals(ObservanceId::parse($slug)));
    }

    /**
     * @return array<string, array{string}>
     */
    public function validSlugs(): array
    {
        return [
            'sanctoral saint' => ['roman:sanctorale:laurentius'],
            'sanctoral with provenance' => ['roman:sanctorale:osb:benedictus'],
            'sanctoral composite' => ['roman:sanctorale:petrus-paulus'],
            'votive lady on saturday' => ['roman:votive:maria-in-sabbato'],
            'temporal advent sunday' => ['roman:temporale:advent:sunday-3'],
            'temporal paschal named' => ['roman:temporale:paschal:ash-wednesday'],
            'temporal ferial coordinate' => ['roman:temporale:paschal:feria-5-week-2'],
            'temporal civil-fixed' => ['roman:temporale:civil-fixed:rogation-major'],
            'temporal month-computed' => ['roman:temporale:month-computed:ember-september-sat'],
        ];
    }

    /**
     * @dataProvider invalidSlugs
     */
    public function testRejectsMalformedSlugs(string $slug): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObservanceId::parse($slug);
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidSlugs(): array
    {
        return [
            'uppercase' => ['roman:sanctorale:Laurentius'],
            'space' => ['roman:sanctorale:st laurentius'],
            'underscore' => ['roman:sanctorale:laurentius_m'],
            'diacritic/ligature' => ['roman:sanctorale:cæcilia'],
            'empty segment' => ['roman::laurentius'],
            'trailing colon' => ['roman:sanctorale:laurentius:'],
            'leading colon' => [':roman:sanctorale:laurentius'],
            'unknown cycle' => ['roman:proprium:laurentius'],
            'bare cycle (no body)' => ['roman:sanctorale'],
            'missing rite' => ['sanctorale:laurentius'],
            'unknown rite' => ['byzantine:menaion:pascha'],
            'temporal unknown anchor family' => ['roman:temporale:foobar:sunday-1'],
            'easter-offset as anchor' => ['roman:temporale:easter-offset:46'],
            'easter-offset as slot (Route 1)' => ['roman:temporale:paschal:easter-offset:46'],
        ];
    }

    public function testAccessorsForSanctoral(): void
    {
        $id = ObservanceId::parse('roman:sanctorale:laurentius');

        self::assertTrue($id->rite()->isRoman());
        self::assertTrue($id->cycle()->isSanctorale());
        self::assertNull($id->anchorFamily());
        self::assertSame(['laurentius'], $id->segments());
    }

    public function testAccessorsForTemporal(): void
    {
        $id = ObservanceId::parse('roman:temporale:advent:sunday-3');

        self::assertTrue($id->cycle()->isTemporale());
        $anchor = $id->anchorFamily();
        self::assertInstanceOf(AnchorFamily::class, $anchor);
        self::assertSame(AnchorFamily::ADVENT, $anchor->value());
        self::assertSame(['advent', 'sunday-3'], $id->segments());
    }

    public function testOfBuildsFromSegments(): void
    {
        $id = ObservanceId::of('roman', 'sanctorale', 'laurentius');

        self::assertSame('roman:sanctorale:laurentius', $id->toString());
    }

    public function testEqualsIsPureStringIdentity(): void
    {
        $a = ObservanceId::parse('roman:sanctorale:laurentius');
        $b = ObservanceId::parse('roman:sanctorale:laurentius');
        $c = ObservanceId::parse('roman:sanctorale:agnes-virgin-martyr');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
