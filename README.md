# Directorium Core

> Liturgical-calendar engine for the traditional Roman rite — *Introíbo ad altáre Dei.*

Directorium **Core** is a clean-room PHP library that computes the traditional Roman liturgical
calendar. For any date it resolves the office of the day, its rank/class, liturgical colour,
season, commemorations, and the occurrence/concurrence between them — beginning with the
**1962 Roman** rubrics (= 1960) and growing to the other traditional systems. It is the
authoritative engine behind the Directorium API, the directorium.app website, and the Ordo WordPress
plugin.

The engine is **independently validated** day-by-day against external oracles (the missalemeum
calendar, the SSPX ordo feed, and Divinum Officium's reference implementation), so its output is
provably correct across centuries. The compiled calendar corpus ships as generated static data.

## Status

Pre-release — **v0.1.0 in progress**. See the [roadmap](ROADMAP.md).

## Install

```bash
composer require directorium/core
```

Requires PHP 7.4+. No runtime dependencies.

## Usage

Two free functions in the `Directorium\Core` namespace are the entry points:

```php
use function Directorium\Core\day;
use function Directorium\Core\contract;

// The resolved office as typed value objects:
$day = day(new DateTimeImmutable('2026-06-30'));
foreach ($day->celebration() as $office) {
    echo $office->id()->toString(), ' — ', $office->rank()->label(), "\n";
}

// The versioned, JSON-ready output contract (what the Api/Site/Ordo build on):
$c = contract(new DateTimeImmutable('2026-06-30'), false, 'sspx', '1954');
echo $c['edition'];               // roman:divino-afflatu
echo $c['celebration'][0]['id'];  // the winning office
```

Both take optional `$calendar` (particular-calendar overlay) and `$rubricSystem`
(edition) selectors. The **[integration guide](docs/integration-guide.md)** covers the
full API and worked examples; **[calendars and editions](docs/calendars-and-editions.md)**
explains the editions (1962/1954/1955) and overlays (SSPX/FSSP/ICKSP) and how to select
them.

## Output contract

`contract()` returns the versioned, JSON-ready **output contract** — the public shape
the Api, Site, and Ordo repos build on. It is versioned (currently `1.0.2`) and grows
only additively within 1.x. Every field, the three-version provenance scheme, the
stability guarantees, and worked examples are documented in
[docs/design/output-contract.md](docs/design/output-contract.md).

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

© 2026 Directorium. Licensed under **AGPL-3.0-or-later** (see [LICENSE](LICENSE)). The compiled
calendar dataset is released under **CC0**.
