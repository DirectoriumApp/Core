<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Edition\RubricSystem;
use InvalidArgumentException;

/**
 * The registry of liturgical-season tokens and the per-edition subset each rubric
 * system admits.
 *
 * `season` is an **open, edition-scoped vocabulary** (contract #364,
 * docs/design/season-vocabulary.md): a token is bare and shared wherever the
 * liturgical concept is shared — `advent` means the same thing in every edition, so
 * the comparison diff aligns seasons by token with no per-edition remapping — while
 * the set of tokens a given edition may emit is that edition's declared subset,
 * namespaced by the contract's `edition` field.
 *
 * Every rubric system built today (1954 Divino Afflatu, 1955 interim, 1962 Rubricae
 * 1960) shares the traditional eight-tempus subset, so the union equals that subset
 * and the reclassification is byte-identical for the editions that exist. A later
 * edition with different tempora — the Novus Ordo drops Septuagesima and Passiontide
 * and adds `ordinary-time` — registers its own subset here; a new token is a MINOR
 * contract bump by the open-enum rule (docs/design/output-contract.md), never a
 * breaking one. This class is the single source of truth {@see Season} validates
 * against and that per-edition discovery (the calendar-mode comparison, the Api's
 * `/meta`) reads.
 */
final class SeasonVocabulary
{
    /**
     * The traditional eight tempora, in calendar order — the subset shared by every
     * edition built today. Editions register the subset they admit in {@see SUBSETS}.
     *
     * @var list<string>
     */
    private const TRADITIONAL = [
        Season::ADVENT,
        Season::CHRISTMASTIDE,
        Season::EPIPHANY,
        Season::SEPTUAGESIMA,
        Season::LENT,
        Season::PASSIONTIDE,
        Season::EASTERTIDE,
        Season::PENTECOST,
    ];

    /**
     * The Novus-Ordo (Ordinary-Form) subset — Advent, Christmas Time, Ordinary Time,
     * Lent, and Easter Time. It drops Septuagesima, Passiontide, and the distinct
     * Time-after-Epiphany / Time-after-Pentecost, and adds `ordinary-time`, the one
     * genuinely new token. `advent`/`lent`/`eastertide` are the same bare tokens the
     * traditional editions use, so the comparison diff aligns seasons by token with no
     * remapping (docs/design/novus-ordo-calendar-model.md).
     *
     * @var list<string>
     */
    private const NOVUS_ORDO = [
        Season::ADVENT,
        Season::CHRISTMASTIDE,
        Season::ORDINARY_TIME,
        Season::LENT,
        Season::EASTERTIDE,
    ];

    /**
     * The season subset each rubric system admits, keyed by edition URN. The three
     * traditional editions share {@see TRADITIONAL}; the Novus Ordo (built snapshot
     * `roman:novus-ordo-2002`) registers {@see NOVUS_ORDO}. A reserved snapshot
     * (1969 / 1975) registers its subset when it is built.
     *
     * @var array<string, list<string>>
     */
    private const SUBSETS = [
        RubricSystem::DIVINO_AFFLATU => self::TRADITIONAL,
        RubricSystem::RUBRICAE_1955 => self::TRADITIONAL,
        RubricSystem::RUBRICAE_1960 => self::TRADITIONAL,
        RubricSystem::NOVUS_ORDO_2002 => self::NOVUS_ORDO,
    ];

    /**
     * The open union of every registered season token across all editions — the set
     * {@see Season::fromString()} accepts. Adding a member is a minor contract bump.
     *
     * @return list<string>
     */
    public static function tokens(): array
    {
        $tokens = [];
        foreach (self::SUBSETS as $subset) {
            foreach ($subset as $token) {
                if (!in_array($token, $tokens, true)) {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /** Whether a token is registered in the open union (valid for at least one edition). */
    public static function isRegistered(string $token): bool
    {
        return in_array($token, self::tokens(), true);
    }

    /**
     * The season tokens a given edition admits, in calendar order.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException if the edition is not registered
     */
    public static function subsetFor(string $edition): array
    {
        if (!isset(self::SUBSETS[$edition])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown edition "%s"; registered editions: %s.',
                $edition,
                implode(', ', array_keys(self::SUBSETS))
            ));
        }

        return self::SUBSETS[$edition];
    }

    /**
     * Whether an edition admits a season token.
     *
     * @throws InvalidArgumentException if the edition is not registered (via {@see subsetFor()})
     */
    public static function permits(string $edition, string $token): bool
    {
        return in_array($token, self::subsetFor($edition), true);
    }

    /**
     * The registered editions, in historical order.
     *
     * @return list<string>
     */
    public static function editions(): array
    {
        return array_keys(self::SUBSETS);
    }
}
