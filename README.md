# Introibo Core

> Liturgical-calendar engine for the traditional Roman rite — *Introíbo ad altáre Dei.*

Introibo **Core** is a clean-room PHP library that computes the traditional Roman liturgical
calendar. For any date it resolves the office of the day, its rank/class, liturgical colour,
season, commemorations, and the occurrence/concurrence between them — beginning with the
**1962 Roman** rubrics (= 1960) and growing to the other traditional systems. It is the
authoritative engine behind the Introibo API, the introibo.org website, and the Ordo WordPress
plugin.

The engine is **independently validated** day-by-day against external oracles (the missalemeum
calendar, the SSPX ordo feed, and Divinum Officium's reference implementation), so its output is
provably correct across centuries. The compiled calendar corpus ships as generated static data.

## Status

Pre-release — **v0.1.0 in progress**. See the [roadmap](ROADMAP.md).

## Install

```bash
composer require introibo/core
```

Requires PHP 7.4+. No runtime dependencies.

## Usage (target API)

```php
use Introibo\Core\LiturgicalCalendar;

$day = (new LiturgicalCalendar())->day(new DateTimeImmutable('2026-06-30'));
echo $day->title;        // the office of the day
echo $day->rank;         // class (I–IV)
print_r($day->colors);   // liturgical colour(s)
```

## Development

Work branches off `develop`, lands via squash PRs with Conventional-Commit titles, and releases
are cut automatically by release-please. Local checks once the code lands:

```bash
composer install
composer exec phpcs        # PSR-12
composer exec phpstan      # static analysis
php tests/validate-oracle.php tests/fixtures/oracle.json   # day-by-day regression
```

## Licence

© 2026 Introibo. Licensed under **AGPL-3.0-or-later** (see [LICENSE](LICENSE)). The compiled
calendar dataset is released under **CC0**.
