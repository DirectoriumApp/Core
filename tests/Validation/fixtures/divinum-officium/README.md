# Divinum Officium oracle — 1954 & 1955 editions

Validation fixtures for the historical editions (Epic #68, issue #71): a pinned,
day-resolved cross-check of the engine's **1954 (Divino Afflatu)** and **1955 (Cum
nostra hac aetate)** resolution against the [Divinum Officium](https://github.com/DivinumOfficium/divinum-officium)
project's independent Perl implementation.

Divinum Officium is an independent witness to the uncopyrightable facts of the
traditional calendar (rank, feast, precedence). It is **never a data source and never
transcribed** — the corpus authors those facts from its own cited sources (`ordo-1954`,
`cn-1955`) and DO is run only as an oracle. See `sources.yaml` keys `do-1955` and the
optional live adapter `tests/Validation/DivinumOfficiumOracle.php`.

## Files

- **`divino-afflatu-1954.ndjson`** — engine ↔ DO `Divino Afflatu - 1954` agreement rows.
- **`reduced-1955.ndjson`** — engine ↔ DO `Reduced - 1955` agreement rows.
- **`known-differences.ndjson`** — the tracked, categorised differences the grade-level
  calendar engine does not (yet) resolve: the finer per-day-type Tabella (an apostle's
  second-class feast outranking a per-annum Sunday), the 1955-instituted feasts the
  grade-only derive does not carry (St Joseph the Worker), one single-feast grade
  disagreement (the Lateran dedication), and the deferred temporal / Office-of-the-Dead
  categories shared with the 1954 engine.

Each agreement row is `{date, id, grade, do}`: the civil date, the engine's celebrated
observance id and legacy grade, and the DO office title `~` grade the value was confirmed
against. {@see \Directorium\Core\Tests\Validation\HistoricalEditionOracleTest} resolves the
day through `DayResolver::forEdition()` and asserts the id and grade still match — a static,
CI-enforced regression gate that needs neither Perl nor a DO checkout at test time.

## Coverage & provenance

The rows are a curated cross-section of the full-year day-by-day comparison (1954, 1957,
1958) run at authoring time with the vendored DO bridge
(`tools/oracles/divinum-officium-bridge.pl`), version strings `Divino Afflatu - 1954` and
`Reduced - 1955`. They span the grade ladder (Duplex I/II class, greater double, simple)
and the reform's signature changes:

- **the semidouble reduction** — St Alexius (17 Jul) is `semiduplex` under 1954 and
  `simplex` under 1955 (Title II.20), the same feast on both fixtures;
- **a mystery of the Lord over a per-annum Sunday** — the Exaltation of the Cross
  (14 Sep 1958) is celebrated over the Sunday it commemorates (Title II.7);
- **the retained first-class feasts and vigils**, and the second-class apostles' feasts.

The 1954 fixed-date sanctoral was proven day-by-day across two years in Epic #64 (zero
grade discrepancies vs DO); this fixture pins a representative subset so the proof rides
in CI. To refresh or extend, re-run the bridge for the chosen dates and update the rows.
