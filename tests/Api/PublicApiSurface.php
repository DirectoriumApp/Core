<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Api;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * The frozen public API surface (#89). This enumerates every class and function a
 * downstream consumer (the Api, Site, Ordo) may build on, and renders a canonical
 * text snapshot of their public constants and method signatures. {@see PublicApiSnapshotTest}
 * pins that snapshot; a change to it means a change to the public API — additive within
 * 1.x, breaking only at a major version (see docs/api-stability.md).
 *
 * Everything under `Directorium\Core\` NOT listed here is internal implementation
 * detail, not covered by the compatibility guarantee.
 */
final class PublicApiSurface
{
    /** The public free functions — the primary entry points. */
    public const FUNCTIONS = [
        'Directorium\\Core\\contract',
        'Directorium\\Core\\day',
        'Directorium\\Core\\explain',
    ];

    /** The public classes and interfaces, in the order rendered (kept sorted). */
    public const CLASSES = [
        'Directorium\\Core\\Attribute\\Colour',
        'Directorium\\Core\\Attribute\\ElementColour',
        'Directorium\\Core\\Attribute\\RankClass',
        'Directorium\\Core\\Calendar\\LiturgicalDay',
        'Directorium\\Core\\Calendar\\RealizedObservance',
        'Directorium\\Core\\Compare\\CalendarComparator',
        'Directorium\\Core\\Compare\\CalendarComparison',
        'Directorium\\Core\\Compare\\ComparedDay',
        'Directorium\\Core\\Compare\\ComparedSequence',
        'Directorium\\Core\\Compare\\ComparisonField',
        'Directorium\\Core\\Compare\\EditionDayCell',
        'Directorium\\Core\\Compare\\SequenceComparator',
        'Directorium\\Core\\Compare\\SequencePoint',
        'Directorium\\Core\\Contract\\CalendarDescriptor',
        'Directorium\\Core\\Contract\\DayContract',
        'Directorium\\Core\\Corpus\\Corpus',
        'Directorium\\Core\\Directorium',
        'Directorium\\Core\\Edition\\RubricSystem',
        'Directorium\\Core\\Observance\\ObservanceId',
        'Directorium\\Core\\Observance\\ObservanceKind',
        'Directorium\\Core\\Overlay\\CalendarCatalog',
        'Directorium\\Core\\Temporal\\Season',
        'Directorium\\Core\\Temporal\\SeasonVocabulary',
    ];

    public static function fixturePath(): string
    {
        return __DIR__ . '/public-api-surface.txt';
    }

    /** The canonical text rendering of the whole public surface. */
    public static function snapshot(): string
    {
        $blocks = ['# Public functions'];
        foreach (self::FUNCTIONS as $function) {
            $blocks[] = self::renderSignature(
                'function ' . $function,
                (new ReflectionFunction($function))->getParameters(),
                (new ReflectionFunction($function))->getReturnType()
            );
        }

        $blocks[] = '';
        $blocks[] = '# Public classes';
        foreach (self::CLASSES as $class) {
            $blocks[] = self::renderClass(new ReflectionClass($class));
        }

        return implode("\n", $blocks) . "\n";
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function renderClass(ReflectionClass $class): string
    {
        $kind = $class->isInterface() ? 'interface' : ($class->isFinal() ? 'final class' : 'class');
        $lines = [$kind . ' ' . $class->getName()];

        $constants = [];
        foreach ($class->getReflectionConstants() as $constant) {
            if ($constant->isPublic()) {
                $constants[] = '  const ' . $constant->getName();
            }
        }
        sort($constants);
        $lines = array_merge($lines, $constants);

        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $prefix = '  ' . ($method->isStatic() ? 'static ' : '') . $method->getName();
            $methods[$method->getName()] = self::renderSignature(
                $prefix,
                $method->getParameters(),
                $method->getReturnType(),
                $method->getDeclaringClass()->getName()
            );
        }
        ksort($methods);
        $lines = array_merge($lines, array_values($methods));

        return implode("\n", $lines);
    }

    /**
     * @param list<ReflectionParameter> $parameters
     */
    private static function renderSignature(
        string $head,
        array $parameters,
        ?ReflectionType $returnType,
        string $selfClass = ''
    ): string {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->hasType() ? self::renderType($parameter->getType(), $selfClass) . ' ' : '';
            $rendered[] = $type
                . ($parameter->isVariadic() ? '...' : '')
                . '$' . $parameter->getName()
                . ($parameter->isOptional() ? ' = …' : '');
        }

        $return = $returnType !== null ? ': ' . self::renderType($returnType, $selfClass) : '';

        return $head . '(' . implode(', ', $rendered) . ')' . $return;
    }

    private static function renderType(?ReflectionType $type, string $selfClass): string
    {
        if (!$type instanceof ReflectionNamedType) {
            return $type !== null ? (string) $type : 'mixed';
        }

        $name = $type->getName();

        // Render version-independently: ReflectionNamedType::getName() returns the literal
        // `self`/`static` on PHP <8.4 but the resolved declaring-class FQCN on >=8.4. Normalise
        // both to the FQCN so the frozen snapshot matches on every CI PHP version.
        if (($name === 'self' || $name === 'static') && $selfClass !== '') {
            $name = $selfClass;
        }

        $nullable = $type->allowsNull() && $name !== 'null' && $name !== 'mixed';

        return ($nullable ? '?' : '') . $name;
    }
}
