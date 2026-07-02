<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Citation;

use Introibo\Core\Citation\Citation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CitationTest extends TestCase
{
    public function testParsesABareSourceKey(): void
    {
        $citation = Citation::parse('mr-1920');

        self::assertSame('mr-1920', $citation->sourceKey());
        self::assertNull($citation->locator());
        self::assertSame('introibo:source:mr-1920', $citation->sourceUrn());
        self::assertSame('mr-1920', $citation->toString());
    }

    public function testParsesAKeyWithALocator(): void
    {
        $citation = Citation::parse('mr-1920:p.42');

        self::assertSame('mr-1920', $citation->sourceKey());
        self::assertSame('p.42', $citation->locator());
        self::assertSame('introibo:source:mr-1920', $citation->sourceUrn());
        self::assertSame('mr-1920:p.42', $citation->toString());
    }

    public function testALocatorKeepsAnyFurtherColons(): void
    {
        $citation = Citation::parse('rg-1960:n.91:2');

        self::assertSame('rg-1960', $citation->sourceKey());
        self::assertSame('n.91:2', $citation->locator());
    }

    public function testEqualsComparesKeyAndLocator(): void
    {
        self::assertTrue(Citation::parse('mr-1920')->equals(new Citation('mr-1920')));
        self::assertTrue(Citation::parse('mr-1920:p.1')->equals(new Citation('mr-1920', 'p.1')));
        self::assertFalse(Citation::parse('mr-1920')->equals(new Citation('rg-1960')));
        self::assertFalse(Citation::parse('mr-1920:p.1')->equals(new Citation('mr-1920', 'p.2')));
        self::assertFalse(Citation::parse('mr-1920:p.1')->equals(new Citation('mr-1920')));
    }

    public function testRejectsAnEmptySourceKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Citation('');
    }

    public function testRejectsAnEmptyLocator(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Citation('mr-1920', '');
    }

    public function testStringableYieldsTheReferenceForm(): void
    {
        self::assertSame('mr-1920:p.42', (string) Citation::parse('mr-1920:p.42'));
    }
}
