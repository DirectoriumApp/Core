<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Discipline;

use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Discipline\PenitentialDiscipline;
use PHPUnit\Framework\TestCase;

/**
 * The penitential discipline read from the corpus (#248): the 1917 Code loaded from
 * `disciplines/cic-1917/`, exposing its rules and its fasting vigils.
 */
final class PenitentialDisciplineTest extends TestCase
{
    private static function cic1917(): PenitentialDiscipline
    {
        return PenitentialDiscipline::fromCorpus(Corpus::default(), 'cic-1917');
    }

    public function testMetaFromTheCorpus(): void
    {
        $discipline = self::cic1917();

        self::assertSame('roman:cic-1917', $discipline->urn());
        self::assertSame('1917 Code of Canon Law', $discipline->name());
    }

    public function testRulesCarryTheirObligationAndCitation(): void
    {
        $discipline = self::cic1917();

        self::assertTrue($discipline->definesRule('friday'));
        $friday = $discipline->rule('friday');
        self::assertNotNull($friday);
        self::assertFalse($friday['fast']);
        self::assertSame('full', $friday['abstinence']->value());
        self::assertSame('cic-1917:c1252', $friday['cite']);

        $lentMinor = $discipline->rule('lent-minor');
        self::assertNotNull($lentMinor);
        self::assertTrue($lentMinor['fast']);
        self::assertSame('partial', $lentMinor['abstinence']->value());
    }

    public function testAnUndeclaredRuleIsNull(): void
    {
        // A later, laxer discipline simply omits rules it drops; an unknown rule is null.
        self::assertFalse(self::cic1917()->definesRule('rogation-day'));
        self::assertNull(self::cic1917()->rule('rogation-day'));
    }

    public function testTheFastingVigils(): void
    {
        $discipline = self::cic1917();

        self::assertTrue($discipline->isFastingVigil('roman:temporale:christmas:vigil'));
        self::assertTrue($discipline->isFastingVigil('roman:temporale:paschal:pentecost-vigil'));
        self::assertTrue($discipline->isFastingVigil('roman:sanctorale:assumptio:vigilia'));
        self::assertTrue($discipline->isFastingVigil('roman:sanctorale:omnium-sanctorum:vigilia'));

        // A vigil the 1917 discipline does not keep as a fast (e.g. St Lawrence's).
        self::assertFalse($discipline->isFastingVigil('roman:sanctorale:laurentius:vigilia'));
    }
}
