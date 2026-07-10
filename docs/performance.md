# Performance

Resolution is designed for the Api's high-volume `day()` traffic. The work is cached
at three levels so a warm request is a near-free lookup and even a cold request pays the
corpus parse and year build only once. This document records the budget, the caches,
and how to measure them. No cache ever changes resolved output — that is proven by the
golden suite and by `CacheConsistencyTest`.

## Budget

`php bin/benchmark.php` measures four paths against a budget (median of 5 iterations,
leap year 2024 / 366 days). Representative figures on a developer machine, with the
committed budgets (generous headroom for slower CI runners):

| Path | What it costs | Typical | Budget |
| --- | --- | --- | --- |
| `cold-day` | one day in a fresh process — corpus parse + full-year build + serialise | ~13 ms | 1500 ms |
| `warm-day` | one day from an already-resolved year (a cache hit) | ~0.01 ms | 5 ms |
| `full-year` | every day of a year resolved and serialised | ~17 ms | 4000 ms |
| `year-range` (per year) | consecutive years, corpus parsed once | ~15 ms | 1500 ms |

The benchmark exits non-zero if any path is over budget, so it can gate a manual perf
check. It is deliberately **not** a CI job — shared-runner timing is too noisy for a
hard gate; the byte-stability guarantees below are what CI enforces.

## The caches

1. **Parsed corpus** — [`Corpus`](../src/Corpus/Corpus.php) parses each NDJSON/JSON file
   once and holds the rows in a process-wide static cache keyed by path. The corpus is
   immutable at runtime, so constructing a data source per resolved year stays cheap and
   no file is read twice. This is why `year-range` amortises to a flat per-year cost.
2. **Resolved year** — [`resolvedYear()`](../src/functions.php) memoises the whole
   `ResolvedYear` per `(year, calendar, rubric-system)`, so repeated `day()`/`contract()`
   calls for the same year are O(1) lookups. `explainedYear()` memoises separately, so
   turning on the resolution trace never perturbs the default (golden-hashed) resolution.
3. **Paschal offsets** — [`PaschalSkeleton`](../src/Temporal/PaschalSkeleton.php) holds
   the Easter-relative offset table statically; each year's skeleton is derived from
   Easter with plain date arithmetic. The temporal cycle for a year is built **once** per
   `resolveYear`, not per day.

## Cache invalidation

The corpus is immutable per process, so callers never invalidate in normal operation. A
long-lived process that must pick up a new **data-version** can drop the parsed-corpus
caches with [`Corpus::flush()`](../src/Corpus/Corpus.php); the next resolution reparses
from disk. The `resolvedYear()` memo lives for the process, so a data-version change is
otherwise handled by starting a fresh worker (which is how the Api rolls versions). The
Api layer keys its own HTTP cache on the data-version stamp.

## Correctness under caching

- `CacheConsistencyTest` resolves a full year warm, calls `Corpus::flush()`, resolves it
  again cold, and asserts the two are **byte-identical** (a sha256 over every day's
  contract) for both 1962 and 1954 — the cache cannot alter output.
- The golden-year digest fixture and the per-edition oracle suites prove the resolved
  output itself is stable, so an optimisation that changed a byte would fail CI.
