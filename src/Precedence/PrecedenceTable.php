<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use Introibo\Core\Corpus\Corpus;
use RuntimeException;

/**
 * The read seam for an edition's precedence rules (#43): the Table of Liturgical
 * Days (the tier ordinals), the named membership id-sets the tier derivation
 * branches on, and the per-class commemoration limits — read from the corpus
 * rather than held as literals in {@see Rubrics1962Precedence}.
 *
 * This makes the resolver genuinely rubric-generic: it asks the table for the tier
 * of a selector, whether an id belongs to a set, and how many commemorations a
 * class admits, so a later rules-family (1954, 1955, the Novus Ordo) supplies its
 * own table with no engine edit — the same swap the sanctoral and temporal seams
 * were built for. Depends only on {@see Corpus} (no Calendar types), so the
 * Precedence layer stays acyclic.
 *
 * The parsed table is memoised for the life of the process (the corpus is
 * immutable at runtime), keyed by the reader's identity.
 */
final class PrecedenceTable
{
    /** The path-safe directory key for the 1962 edition inside the corpus tree. */
    private const EDITION_DIR = 'roman-rubricae-1960';

    /**
     * @var array<string, array{
     *     tiers: array<string, PrecedenceTier>,
     *     members: array<string, array<string, true>>,
     *     limits: array<int, int>
     * }> Parsed tables, keyed by corpus base.
     */
    private static array $tables = [];

    private Corpus $corpus;

    private string $cacheKey;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
        // Keyed by the corpus root, not object identity: the table is a pure function
        // of the data on disk, so two readers over the same tree share the parse and a
        // fixture tree never collides with the shipped one.
        $this->cacheKey = $this->corpus->baseDir();
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * The precedence tier of a selector — its line in the Table of Liturgical Days.
     *
     * @throws RuntimeException if the selector is not in the table
     */
    public function tier(string $selector): PrecedenceTier
    {
        $tiers = $this->table()['tiers'];
        if (!isset($tiers[$selector])) {
            throw new RuntimeException(sprintf('Precedence table has no tier "%s".', $selector));
        }

        return $tiers[$selector];
    }

    /**
     * Whether an observance id belongs to a named membership set.
     *
     * @throws RuntimeException if the set is not defined in the table
     */
    public function isMember(string $set, string $id): bool
    {
        $members = $this->table()['members'];
        if (!isset($members[$set])) {
            throw new RuntimeException(sprintf('Precedence table has no membership set "%s".', $set));
        }

        return isset($members[$set][$id]);
    }

    /**
     * How many commemorations a day of the given class (1 = highest) admits.
     *
     * @throws RuntimeException if the class has no limit in the table
     */
    public function commemorationLimit(int $dayClass): int
    {
        $limits = $this->table()['limits'];
        if (!isset($limits[$dayClass])) {
            throw new RuntimeException(sprintf('Precedence table has no commemoration limit for class %d.', $dayClass));
        }

        return $limits[$dayClass];
    }

    /**
     * @return array{
     *     tiers: array<string, PrecedenceTier>,
     *     members: array<string, array<string, true>>,
     *     limits: array<int, int>
     * }
     */
    private function table(): array
    {
        if (isset(self::$tables[$this->cacheKey])) {
            return self::$tables[$this->cacheKey];
        }

        $tiers = [];
        foreach ($this->corpus->precedenceTiers(self::EDITION_DIR) as $row) {
            $tiers[$this->requireString($row, 'selector')] = PrecedenceTier::of(
                $this->requireInt($row, 'ordinal'),
                $this->requireInt($row, 'subOrder')
            );
        }

        $members = [];
        $limits = [];
        foreach ($this->corpus->precedenceRules(self::EDITION_DIR) as $row) {
            $rule = $this->requireString($row, 'rule');
            if ($rule === 'membership') {
                $members[$this->requireString($row, 'name')] = $this->idSet($row);
            } elseif ($rule === 'commemoration-limit') {
                $limits[$this->requireInt($row, 'dayClass')] = $this->requireInt($row, 'limit');
            } else {
                throw new RuntimeException(sprintf('Precedence rules carry an unknown rule "%s".', $rule));
            }
        }

        self::$tables[$this->cacheKey] = ['tiers' => $tiers, 'members' => $members, 'limits' => $limits];

        return self::$tables[$this->cacheKey];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, true>
     */
    private function idSet(array $row): array
    {
        $ids = $row['ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            throw new RuntimeException(sprintf(
                'Precedence membership set "%s" has no ids.',
                $this->requireString($row, 'name')
            ));
        }

        $set = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                throw new RuntimeException(sprintf('Precedence membership set "%s" has a malformed id.', $row['name']));
            }
            $set[$id] = true;
        }

        return $set;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Precedence record is missing string field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException(sprintf('Precedence record is missing integer field "%s".', $key));
        }

        return $value;
    }
}
