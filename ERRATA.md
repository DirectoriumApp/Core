# Errata

Confirmed corrections to Directorium's liturgical data, published openly. Authority comes from owning our
mistakes: when a datum is found to be wrong and fixed, it is recorded here and the dataset's **data-version**
is bumped, so consumers can tell whether their cached data predates a fix.

Each entry records **what was wrong**, **since when**, **the fix**, **the citation** that establishes the
correct value, and **who reviewed it**. A machine-readable mirror (`errata.json`) is generated for the API
and the public errata page once the corpus lands.

## How a correction happens

1. A **Liturgical correction** issue is opened with the printed citation (see the issue template).
2. A liturgical-data reviewer verifies the citation and, where possible, an independent oracle.
3. The data is fixed; this file and `errata.json` get an entry; the data-version is bumped.

## Entries

_No errata yet — the corpus has not been generated. Entries will use the format below._

```
### YYYY-MM-DD — <short title>
- **Affected:** <observance / date / edition>
- **Was:** <incorrect value>
- **Now:** <correct value>
- **Since:** data-version <x> · **Fixed in:** data-version <y>
- **Citation:** <source key + rubric/page> (see SOURCES.md)
- **Corroboration:** <independent oracle, if any>
- **Reviewed by:** <reviewer>
- **Issue:** #<n>
```
