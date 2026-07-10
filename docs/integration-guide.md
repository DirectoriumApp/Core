# Core integration guide

Directorium **Core** resolves the traditional Roman liturgical calendar for any date:
the office of the day, its rank/class, colour, season, commemorations, the
occurrence/concurrence between them, plus the calendrical and fasting attributes of the
day. This guide covers installing it, the public API, the selectors, and the output
contract, with worked examples. For what the editions and overlays *mean*, see
[calendars-and-editions.md](calendars-and-editions.md).

## Install

```bash
composer require directorium/core
```

Requires **PHP 7.4+**. No runtime dependencies; the compiled calendar corpus ships as
generated static data inside the package.

## The public API

Two free functions in the `Directorium\Core` namespace are the stable entry points.

### `day()` — the resolved office as an object

```php
Directorium\Core\day(
    DateTimeImmutable $date,
    ?string $calendar = null,      // particular-calendar overlay; null = universal
    ?string $rubricSystem = null   // edition; null = 1962 (Rubricae 1960)
): Directorium\Core\Calendar\LiturgicalDay
```

`LiturgicalDay` exposes the resolved office as typed value objects — `celebration()`,
`commemoration()`, `tempora()`, `date()`, `secondVespers()`, `fasting()`, and (when
requested) `trace()`. Use it when you want to work with the engine's objects directly.

### `contract()` — the versioned JSON-ready shape

```php
Directorium\Core\contract(
    DateTimeImmutable $date,
    bool $explain = false,         // include the resolution trace
    ?string $calendar = null,
    ?string $rubricSystem = null
): array
```

`contract()` returns the **output contract** — the stable, versioned array the Api,
Site, and Ordo build on. This is what most integrators want. Its full shape (every
field, the three-version provenance, the stability rules) is documented in
[design/output-contract.md](design/output-contract.md); it is versioned `1.0.x` and
grows only additively.

`Directorium\Core\explain($date, $calendar, $rubricSystem)` is `contract()` with
`$explain = true` — it fills the `resolution` slot with the show-your-work trace (which
office won, what became of the ones it beat, each step cited to its rubric).

> `resolvedYear()` / `explainedYear()` also exist but are `@internal` — a memoisation
> seam, not part of the stable API. Build on `day()` / `contract()` / `explain()`.

## Selectors

Both entry points take two orthogonal, optional selectors (see
[calendars-and-editions.md](calendars-and-editions.md) for the meaning of each value):

| Selector | Chooses | Example values | Default |
| --- | --- | --- | --- |
| `$rubricSystem` | the **edition** (rules-family) | `null`, `1962`, `1954`, `1955`, `divino-afflatu`, or an edition URN | 1962 (`roman:rubricae-1960`) |
| `$calendar` | a **particular-calendar overlay** layered over the edition | `null`, `sspx`, `fssp`, `icksp`, `generic-1962`, or an overlay URN | universal (no overlay) |

They compose: an overlay is resolved *over* the chosen edition. Omitting both resolves
the universal 1962 calendar.

## Comparing editions

The `Directorium\Core\Compare` API diffs what two or more editions celebrate — the
calendar-mode comparison (see [design/calendar-comparison-model.md](design/calendar-comparison-model.md)):

```php
use Directorium\Core\Compare\CalendarComparator;
use Directorium\Core\Compare\SequenceComparator;

$day = (new CalendarComparator())->compareDay(['1962', '1954'], $date);   // one date, N editions
$range = (new CalendarComparator())->compareRange(['1962', '1954'], $from, $to);
$slider = (new SequenceComparator())->acrossYears($anchor, 2020, 2030, '1962'); // for the UI slider
```

## Worked examples

### 1 — Today's office

```php
use function Directorium\Core\day;

$today = day(new DateTimeImmutable('2026-06-30'));
foreach ($today->celebration() as $office) {
    echo $office->id()->toString(), "\n";      // e.g. roman:sanctorale:...
    echo $office->rank()->label(), "\n";       // the class
    echo $office->colour()->base()->value();   // the liturgical colour
}
```

### 2 — The JSON contract for a date under the 1954 edition

```php
use function Directorium\Core\contract;

$c = contract(new DateTimeImmutable('2024-08-14'), false, null, '1954');
echo $c['edition'];                    // roman:divino-afflatu
echo $c['celebration'][0]['id'];       // the winning office
echo $c['celebration'][0]['rankOrdinal']; // its class
echo json_encode($c, JSON_PRETTY_PRINT);
```

### 3 — The SSPX particular calendar

```php
$c = contract(new DateTimeImmutable('2026-09-03'), false, 'sspx');
echo $c['calendar']['particular']['id'];   // directorium:overlay:roman:sspx
echo $c['celebration'][0]['id'];           // St Pius X, elevated to I class under SSPX
```

### 4 — Why did this day resolve the way it did?

```php
use function Directorium\Core\explain;

$c = explain(new DateTimeImmutable('2026-11-02')); // All Souls
print_r($c['resolution']);   // the cited, ordered why-this-won trace
```

## Local development

```bash
composer install
composer lint       # PSR-12 (phpcs)
composer analyse    # static analysis (phpstan)
composer test       # PHPUnit
composer validate-oracles   # day-by-day regression vs external oracles
```

The Node corpus generator lives under `tools/generator` (`npm run build` / `npm run
verify`); see [citable-dataset.md](citable-dataset.md).
