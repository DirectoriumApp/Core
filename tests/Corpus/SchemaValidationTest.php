<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Corpus;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Proves the corpus JSON Schemas (draft 2020-12) are executable, not just
 * documentation: every representative sample record validates against its
 * schema, and every deliberately-malformed record is rejected. This is the #39
 * acceptance gate — "a JSON schema validates a sample file" — turned into a
 * living CI check that the generator (#40) and loaders (#41–#43) build on.
 *
 * Schemas live in data/corpus/schema/<base>.schema.json; samples in
 * tests/fixtures/corpus/{valid,invalid}/<base>.ndjson, one record per line.
 */
final class SchemaValidationTest extends TestCase
{
    private const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    /** @var list<string> Every record shape: <base>.schema.json <-> <base>.ndjson. */
    private const SHAPES = [
        'source',
        'identity.sanctorale',
        'identity.temporale',
        'temporal-skeleton',
        'attributes.sanctorale',
        'attributes.temporale',
        'placement.sanctorale',
        'precedence-tier',
        'precedence-rules',
    ];

    /**
     * Each schema is well-formed JSON and declares draft 2020-12.
     */
    public function testEverySchemaIsWellFormedDraft202012(): void
    {
        foreach (self::SHAPES as $shape) {
            $path = self::schemaDir() . '/' . $shape . '.schema.json';
            self::assertFileExists($path, "Missing schema for shape '$shape'.");

            $decoded = json_decode((string) file_get_contents($path));
            self::assertIsObject($decoded, "Schema '$shape' is not a JSON object.");
            self::assertTrue(
                property_exists($decoded, '$schema'),
                "Schema '$shape' has no \$schema keyword."
            );
            self::assertSame(
                self::DRAFT_2020_12,
                $decoded->{'$schema'},
                "Schema '$shape' must declare draft 2020-12."
            );
        }
    }

    /**
     * Every shape ships a non-empty valid and invalid fixture, so neither data
     * provider below can silently degrade to zero cases.
     */
    public function testEveryShapeShipsValidAndInvalidFixtures(): void
    {
        foreach (self::SHAPES as $shape) {
            foreach (['valid', 'invalid'] as $kind) {
                $path = self::fixtureDir() . '/' . $kind . '/' . $shape . '.ndjson';
                self::assertNotEmpty(
                    self::readNdjson($path),
                    "Shape '$shape' has no $kind fixture records."
                );
            }
        }
    }

    /**
     * @dataProvider validRecords
     */
    public function testValidSampleRecordsConform(string $shape, int $line, string $json): void
    {
        $result = self::validateAgainstSchema($shape, $json);
        $reason = self::describe($result->error());
        self::assertTrue(
            $result->isValid(),
            "Valid $shape record on line $line was rejected: $reason"
        );
    }

    /**
     * @dataProvider invalidRecords
     */
    public function testInvalidSampleRecordsAreRejected(string $shape, int $line, string $json): void
    {
        $result = self::validateAgainstSchema($shape, $json);
        self::assertFalse(
            $result->isValid(),
            "Malformed $shape record on line $line was wrongly accepted: $json"
        );
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function validRecords(): iterable
    {
        yield from self::records('valid');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidRecords(): iterable
    {
        yield from self::records('invalid');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    private static function records(string $kind): iterable
    {
        foreach (self::SHAPES as $shape) {
            $path = self::fixtureDir() . '/' . $kind . '/' . $shape . '.ndjson';
            foreach (self::readNdjson($path) as [$line, $json]) {
                yield "$shape#$line" => [$shape, $line, $json];
            }
        }
    }

    private static function validateAgainstSchema(string $shape, string $json): ValidationResult
    {
        $schemaPath = self::schemaDir() . '/' . $shape . '.schema.json';
        $schema = json_decode((string) file_get_contents($schemaPath));
        $data = json_decode($json);

        // A fresh validator per call keeps each self-contained schema's $id out
        // of a shared registry (re-registering the same $id would throw).
        return (new Validator())->validate($data, $schema);
    }

    private static function describe(?ValidationError $error): string
    {
        if ($error === null) {
            return '(no error reported)';
        }

        $formatted = json_encode((new ErrorFormatter())->format($error), JSON_UNESCAPED_SLASHES);

        return $formatted !== false ? $formatted : '(unformattable error)';
    }

    /**
     * Read an NDJSON fixture into [lineNumber, json] pairs, skipping blank lines.
     *
     * @return list<array{int, string}>
     */
    private static function readNdjson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $records = [];
        $lines = explode("\n", (string) file_get_contents($path));
        foreach ($lines as $index => $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                continue;
            }
            $records[] = [$index + 1, $trimmed];
        }

        return $records;
    }

    private static function schemaDir(): string
    {
        return dirname(__DIR__, 2) . '/data/corpus/schema';
    }

    private static function fixtureDir(): string
    {
        return dirname(__DIR__) . '/fixtures/corpus';
    }
}
