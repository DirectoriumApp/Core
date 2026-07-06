<?php

declare(strict_types=1);

namespace Directorium\Core\Corpus;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use RuntimeException;

/**
 * Typed field readers over a decoded corpus row (a JSON object as a PHP array).
 *
 * The corpus is schema-validated at build time, so at read time these helpers assert
 * the same shape defensively and fail loud on any drift — a corpus that does not match
 * the loader is a build/version mismatch, never something to paper over. Shared by the
 * data sources that rebuild value objects from corpus rows ({@see CorpusSanctoralData},
 * {@see \Directorium\Core\Overlay\CorpusOverlayData}) so the parsing lives in one place.
 */
final class CorpusRecord
{
    /**
     * @param array<string, mixed> $row
     */
    public static function requireString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Corpus record is missing string field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function requireInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException(sprintf('Corpus record is missing integer field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function optionalString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Corpus record has a malformed optional field "%s".', $key));
        }

        return $value;
    }

    /**
     * The `{ base, roseAllowed? }` colour object rebuilt into an {@see ElementColour}.
     *
     * @param array<string, mixed> $row
     */
    public static function elementColour(array $row): ElementColour
    {
        $colour = $row['colour'] ?? null;
        if (!is_array($colour) || !isset($colour['base']) || !is_string($colour['base'])) {
            throw new RuntimeException(sprintf(
                'Corpus record %s has no colour base.',
                self::requireString($row, 'id')
            ));
        }

        if (($colour['roseAllowed'] ?? false) === true) {
            return ElementColour::violetWithRose();
        }

        return ElementColour::of(Colour::fromString($colour['base']));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return non-empty-list<string>
     */
    public static function titulars(array $row): array
    {
        $titulars = $row['titulars'] ?? null;
        if (!is_array($titulars) || $titulars === []) {
            throw new RuntimeException(
                sprintf('Corpus record %s has no titulars.', self::requireString($row, 'id'))
            );
        }

        $out = [];
        foreach ($titulars as $titular) {
            if (!is_string($titular)) {
                throw new RuntimeException(sprintf('Corpus record %s has a non-string titular.', $row['id']));
            }
            $out[] = $titular;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    public static function names(array $row): array
    {
        $names = $row['names'] ?? null;
        if (!is_array($names)) {
            throw new RuntimeException(sprintf('Corpus record %s has no names.', self::requireString($row, 'id')));
        }

        $out = [];
        foreach ($names as $locale => $label) {
            if (!is_string($locale) || !is_string($label)) {
                throw new RuntimeException(sprintf('Corpus record %s has a malformed name entry.', $row['id']));
            }
            $out[$locale] = $label;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    public static function cites(array $row): array
    {
        $cites = $row['cites'] ?? [];
        if (!is_array($cites)) {
            throw new RuntimeException('Corpus record has a malformed cites map.');
        }

        $out = [];
        foreach ($cites as $field => $ref) {
            if (!is_string($field) || !is_string($ref)) {
                throw new RuntimeException('Corpus record has a malformed cites entry.');
            }
            $out[$field] = $ref;
        }

        return $out;
    }
}
