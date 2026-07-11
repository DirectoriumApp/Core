<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use InvalidArgumentException;

/**
 * A liturgical season (tempus) of the traditional Roman year, as divided by the
 * 1962 calendar.
 *
 * The season is a structural feature of the temporal cycle — it is reported by
 * the office of the day and consumed by both temporal fill and the output
 * contract, so it needs one stable machine name rather than free strings.
 *
 * The traditional year has eight seasons. Two of them are easily confused with
 * their neighbours and are the reason a typed season exists: Septuagesima is
 * the distinct pre-Lenten fore-season (violet, no Alleluia), part neither of
 * the Christmas cycle nor of Lent proper; and Passiontide is the last two weeks
 * of Lent — Passion Week and Holy Week — treated as its own tempus, not merely
 * "Lent".
 *
 * The season vocabulary is **open and edition-scoped** ({@see SeasonVocabulary},
 * docs/design/season-vocabulary.md): each token is bare and shared wherever the
 * concept is shared, and each edition declares the subset it admits. Every edition
 * built today shares the traditional eight tempora named below as constants and
 * factories, so validation delegates to the registry rather than a hardcoded set. A
 * later rules-family that drops or adds tempora (the Novus Ordo keeps neither
 * Septuagesima nor Passiontide and adds Ordinary Time) registers its own subset in
 * {@see SeasonVocabulary} — a minor, additive contract change, never a breaking one.
 */
final class Season
{
    public const ADVENT = 'advent';
    public const CHRISTMASTIDE = 'christmastide';
    public const EPIPHANY = 'epiphany';
    public const SEPTUAGESIMA = 'septuagesima';
    public const LENT = 'lent';
    public const PASSIONTIDE = 'passiontide';
    public const EASTERTIDE = 'eastertide';
    public const PENTECOST = 'pentecost';

    /**
     * Ordinary Time (tempus per annum) — the Novus-Ordo green season that has no
     * traditional equivalent, so it is its own token, never a rename of `pentecost`
     * or `epiphany` ({@see SeasonVocabulary}, docs/design/season-vocabulary.md). Only
     * the Novus-Ordo subset admits it.
     */
    public const ORDINARY_TIME = 'ordinary-time';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * A season from any token registered in the open vocabulary (valid for at least
     * one edition). Use {@see forEdition()} when the season must belong to a specific
     * edition's declared subset.
     */
    public static function fromString(string $value): self
    {
        if (!SeasonVocabulary::isRegistered($value)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown liturgical season "%s"; registered seasons: %s.',
                $value,
                implode(', ', SeasonVocabulary::tokens())
            ));
        }

        return new self($value);
    }

    /**
     * A season constrained to an edition's declared subset: the token must be one the
     * given edition admits ({@see SeasonVocabulary}), so a caller building a season for
     * a specific rubric system cannot mint one outside that edition's tempora (e.g. a
     * Novus-Ordo `ordinary-time` under the 1962 edition).
     *
     * @param string $edition the edition URN, e.g. {@see \Directorium\Core\Edition\RubricSystem::RUBRICAE_1960}
     *
     * @throws InvalidArgumentException if the edition does not admit the token
     */
    public static function forEdition(string $value, string $edition): self
    {
        if (!SeasonVocabulary::permits($edition, $value)) {
            throw new InvalidArgumentException(sprintf(
                'Season "%s" is not in the %s vocabulary; valid seasons: %s.',
                $value,
                $edition,
                implode(', ', SeasonVocabulary::subsetFor($edition))
            ));
        }

        return new self($value);
    }

    /**
     * Map a coarse temporal block — a named stretch of the Proper of Time — to
     * the season it belongs to. The mapping is read from the corpus temporal
     * skeleton (#42) via {@see TemporalDefinitions}: a block is a contiguous named
     * stretch of the temporal cycle, and several blocks may share one season
     * (Passion Week and Holy Week are both Passiontide).
     *
     * @throws InvalidArgumentException if the block is not a known temporal block
     */
    public static function forBlock(string $block): self
    {
        $blockSeasons = TemporalDefinitions::default()->blockSeasons();
        if (!isset($blockSeasons[$block])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown temporal block "%s"; valid blocks: %s',
                $block,
                implode(', ', array_keys($blockSeasons))
            ));
        }

        return self::fromString($blockSeasons[$block]);
    }

    public static function advent(): self
    {
        return new self(self::ADVENT);
    }

    public static function christmastide(): self
    {
        return new self(self::CHRISTMASTIDE);
    }

    public static function epiphany(): self
    {
        return new self(self::EPIPHANY);
    }

    public static function septuagesima(): self
    {
        return new self(self::SEPTUAGESIMA);
    }

    public static function lent(): self
    {
        return new self(self::LENT);
    }

    public static function passiontide(): self
    {
        return new self(self::PASSIONTIDE);
    }

    public static function eastertide(): self
    {
        return new self(self::EASTERTIDE);
    }

    public static function pentecost(): self
    {
        return new self(self::PENTECOST);
    }

    public static function ordinaryTime(): self
    {
        return new self(self::ORDINARY_TIME);
    }

    /** The stable machine name used in output. */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
