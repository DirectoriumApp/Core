# Divinum Officium oracle (optional)

[Divinum Officium](https://github.com/DivinumOfficium/divinum-officium) (DO) is a
respected, independent Perl implementation of the traditional office and calendar. The
validation harness (#45) can use a local DO checkout as an optional **tertiary**
cross-check, after the base authority (missalemeum) and the SSPX witness.

It is **optional by design**: Perl and a DO checkout are heavyweight, so the core test
suite never requires them. When DO is not configured, the live comparison skips
cleanly; the pure class parser and the availability/skip behaviour are still covered in
CI.

## What is verified where

| Behaviour | Where |
|---|---|
| The `extractClass()` parser (DO office → 1960 class) | **CI** — unit-tested against representative DO output |
| Graceful skip + actionable reason when DO is absent | **CI** |
| Live engine ↔ DO comparison over a contested-date sample | **Maintainer** — runs only where DO is installed |

The live path cannot run in CI (no Perl/DO there and DO is not meant for automated
network access), so it is maintainer-verified. If DO is installed but its output no
longer matches the parser, the live test **fails loudly** rather than skipping, so the
parser cannot silently rot.

## Enabling the oracle

1. Clone Divinum Officium somewhere:

   ```
   git clone https://github.com/DivinumOfficium/divinum-officium.git
   ```

2. Ensure `perl` is on `PATH`.

3. Point the harness at the checkout and run the validation suite:

   ```
   DIVINUM_OFFICIUM_PATH=/path/to/divinum-officium \
     php vendor/bin/phpunit --filter DivinumOfficiumOracleTest
   ```

`DivinumOfficiumOracle::isAvailable()` gates on both `DIVINUM_OFFICIUM_PATH` (a
directory containing `web/cgi-bin/horas/officium.pl`) and `perl`.

## How it works

`divinum-officium-bridge.pl` invokes DO's own headless entry point exactly as DO's
regression harness does (`regress/scripts/generate-diff.sh`):

```
perl web/cgi-bin/horas/officium.pl "version=Rubrics 1960 - 1960" "command=prayMatutinum" "date=MM-DD-YYYY" "lang2=Latin"
```

Matins carries the resolved day's title and rank. `DivinumOfficiumOracle::extractClass()`
reads the 1960 "N. classis" label from that output and maps it to our class `1..4`. Only
the class is compared — DO is a cross-check on rank, not a fixture of texts.

## First run

DO's exact office rendering can vary by version and rubric. On first enabling the
oracle, run the filtered test above and confirm it does not fail with
"no class was parsed"; if it does, adjust `DivinumOfficiumOracle::extractClass()` to
your DO version's output and re-run.
