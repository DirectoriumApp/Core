<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Temporal\Season;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SeasonTest extends TestCase
{
    /**
     * @dataProvider seasons
     */
    public function testFactoryValueAndRoundTrip(string $value, Season $season): void
    {
        self::assertSame($value, $season->value());
        self::assertSame($value, (string) $season);
        self::assertTrue(Season::fromString($value)->equals($season));
    }

    /**
     * @return array<string, array{string, Season}>
     */
    public function seasons(): array
    {
        return [
            'advent' => [Season::ADVENT, Season::advent()],
            'christmastide' => [Season::CHRISTMASTIDE, Season::christmastide()],
            'epiphany' => [Season::EPIPHANY, Season::epiphany()],
            'septuagesima' => [Season::SEPTUAGESIMA, Season::septuagesima()],
            'lent' => [Season::LENT, Season::lent()],
            'passiontide' => [Season::PASSIONTIDE, Season::passiontide()],
            'eastertide' => [Season::EASTERTIDE, Season::eastertide()],
            'pentecost' => [Season::PENTECOST, Season::pentecost()],
        ];
    }

    public function testFromStringRejectsUnknownSeason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Season::fromString('ordinary');
    }

    /** An edition-scoped season is accepted when the token is in that edition's subset. */
    public function testForEditionAcceptsSeasonInSubset(): void
    {
        $lent = Season::forEdition(Season::LENT, RubricSystem::RUBRICAE_1960);

        self::assertTrue($lent->equals(Season::lent()));
    }

    /** A token absent from the vocabulary is rejected even by an edition that has no such tempus. */
    public function testForEditionRejectsSeasonOutsideEditionVocabulary(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Season::forEdition('ordinary-time', RubricSystem::RUBRICAE_1960);
    }

    /** An unregistered edition is rejected — the vocabulary is scoped to built editions. */
    public function testForEditionRejectsUnknownEdition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Season::forEdition(Season::LENT, 'roman:novus-ordo-2002');
    }

    /**
     * @dataProvider blocks
     */
    public function testForBlockMapsToSeason(string $block, string $expectedSeason): void
    {
        self::assertSame($expectedSeason, Season::forBlock($block)->value());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function blocks(): array
    {
        return [
            'advent' => ['advent', Season::ADVENT],
            'christmastide' => ['christmastide', Season::CHRISTMASTIDE],
            'time-after-epiphany' => ['time-after-epiphany', Season::EPIPHANY],
            'septuagesima' => ['septuagesima', Season::SEPTUAGESIMA],
            'lent' => ['lent', Season::LENT],
            'passiontide' => ['passiontide', Season::PASSIONTIDE],
            'holy-week' => ['holy-week', Season::PASSIONTIDE],
            'eastertide' => ['eastertide', Season::EASTERTIDE],
            'time-after-pentecost' => ['time-after-pentecost', Season::PENTECOST],
        ];
    }

    public function testForBlockRejectsUnknownBlock(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Season::forBlock('time-after-pentecost-week-24-and-a-half');
    }

    /**
     * Septuagesima is its own fore-season: the pre-Lenten block resolves to
     * Septuagesima, not to the Christmas cycle it follows nor to Lent proper.
     */
    public function testSeptuagesimaBoundary(): void
    {
        $season = Season::forBlock('septuagesima');

        self::assertTrue($season->equals(Season::septuagesima()));
        self::assertFalse($season->equals(Season::epiphany()));
        self::assertFalse($season->equals(Season::lent()));
    }

    /**
     * Passiontide is its own tempus covering the last two weeks of Lent, so
     * both Passion Week and Holy Week resolve to Passiontide — never to Lent.
     */
    public function testPassiontideBoundary(): void
    {
        $passionWeek = Season::forBlock('passiontide');
        $holyWeek = Season::forBlock('holy-week');

        self::assertTrue($passionWeek->equals(Season::passiontide()));
        self::assertTrue($holyWeek->equals(Season::passiontide()));
        self::assertTrue($passionWeek->equals($holyWeek));
        self::assertFalse($passionWeek->equals(Season::lent()));
    }
}
