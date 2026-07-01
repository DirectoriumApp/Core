<?php

declare(strict_types=1);

namespace Introibo\Core\Observance;

use InvalidArgumentException;

/**
 * The identity of a liturgical observance — Layer 1 of the identity / edition /
 * year model.
 *
 * This shell holds only edition-invariant identity: the {@see ObservanceId}, the
 * intrinsic {@see ObservanceKind}, the titular subject(s), localized display
 * names (Latin required as the invariant fallback), and non-identity
 * {@see IdentityAliases}.
 *
 * Per-edition attributes (rank, colour, octave, placement, precedence — issues
 * #10–#13 and the edition epics) and per-year realization (resolved date, role,
 * commemorations) attach to this identity in later layers. They are deliberately
 * absent here so one identity stays stable across every year and edition.
 */
final class Observance
{
    private ObservanceId $id;

    private ObservanceKind $kind;

    /** @var non-empty-list<string> Titular subject slug(s): one for a simple feast, more for a composite. */
    private array $titulars;

    /** @var array<string, string> Locale code => display name; 'la' is required. */
    private array $names;

    private IdentityAliases $aliases;

    /**
     * @param list<string>          $titulars One or more titular subject slugs.
     * @param array<string, string> $names    Locale => label; must include a non-empty 'la'.
     */
    public function __construct(
        ObservanceId $id,
        ObservanceKind $kind,
        array $titulars,
        array $names,
        ?IdentityAliases $aliases = null
    ) {
        if ($titulars === []) {
            throw new InvalidArgumentException('An observance requires at least one titular.');
        }
        if (!isset($names['la']) || $names['la'] === '') {
            throw new InvalidArgumentException(
                'An observance requires a Latin ("la") name as the invariant fallback.'
            );
        }

        $this->id = $id;
        $this->kind = $kind;
        $this->titulars = array_values($titulars);
        $this->names = $names;
        $this->aliases = $aliases ?? IdentityAliases::none();
    }

    public function id(): ObservanceId
    {
        return $this->id;
    }

    public function kind(): ObservanceKind
    {
        return $this->kind;
    }

    /** @return non-empty-list<string> */
    public function titulars(): array
    {
        return $this->titulars;
    }

    /** @return array<string, string> */
    public function names(): array
    {
        return $this->names;
    }

    public function latinName(): string
    {
        return $this->names['la'];
    }

    public function name(string $locale): ?string
    {
        return $this->names[$locale] ?? null;
    }

    public function aliases(): IdentityAliases
    {
        return $this->aliases;
    }
}
