# The citable dataset

The compiled corpus under [`data/corpus/`](../data/corpus) is an independent product:
a **CC0-1.0**, byte-reproducible, cited dataset of the traditional Roman liturgical
calendar (the 1954, 1955, and 1962 editions). It is meant to be cited and verified by
scholars and other engines, so it ships as a versioned, checksummed, DOI-bearing
release artifact — separate from the AGPL-3.0-or-later engine that generates it.

## How to cite

Cite a specific version. The canonical machine-readable citation is
[`CITATION.cff`](../CITATION.cff); its `version` tracks each release. Once the
GitHub↔Zenodo integration is enabled (see below), every tagged release is archived to
Zenodo and receives a **per-version DOI** plus a concept DOI for "all versions"; cite
the version DOI for reproducibility.

## Release artifacts

Each tagged release (`release-please` on `main`) attaches, via
[`.github/workflows/release-please.yml`](../.github/workflows/release-please.yml):

| Asset | What it is |
| --- | --- |
| `directorium-dataset-<corpusVersion>.tar.gz` | the whole `data/corpus/` tree — the NDJSON/JSON data, the JSON Schemas, the CC0 `LICENSE.txt`, and the structured `MANIFEST.json` |
| `…​.tar.gz.sha256` | the archive's SHA-256, to verify the download |
| `SHA256SUMS` | one `<sha256>  <path>` line for **every file in the archive**, for `sha256sum -c` |
| `release-metadata.json` | a small summary — the dataset name, `corpusVersion`, file count, and CC0 licence |

The archive is named with the **data-version** (`MANIFEST.json`'s `corpusVersion`,
contract field #54), so a filename pins an exact dataset. `MANIFEST.json` inside carries
the per-file SHA-256s, the record counts, and the source ledger.

## Verifying a download

```sh
# the archive itself
sha256sum -c directorium-dataset-<corpusVersion>.tar.gz.sha256

# every file inside, from the release's SHA256SUMS
tar xzf directorium-dataset-<corpusVersion>.tar.gz
cd corpus && sha256sum -c ../SHA256SUMS
```

## The guarantees behind it

`tools/generator` enforces three properties in CI (the `corpus-verify` job runs
`npm run verify`), so the committed corpus is always a faithful, reproducible build:

1. **Determinism** — two fresh builds are byte-identical.
2. **Freshness** — the committed corpus equals a fresh build (no stale or hand-edited
   file the generator would not reproduce).
3. **Integrity** — every file's SHA-256 matches its `MANIFEST.json` entry (#238) — the
   same check a consumer runs on a download.

Regenerate and re-verify locally with `npm --prefix tools/generator run build` then
`npm --prefix tools/generator run verify`.

## Maintainer step — enabling DOIs

Minting DOIs is a one-time external setup the maintainer performs (it cannot be scripted
from here):

1. Sign in to <https://zenodo.org> with the GitHub account/org and, under **Settings →
   GitHub**, flip the toggle on for `DirectoriumApp/Core`.
2. Cut the next release as usual. Zenodo archives the tagged snapshot (metadata from
   [`.zenodo.json`](../.zenodo.json)) and mints the version + concept DOIs.
3. Add the concept DOI to `CITATION.cff` (`doi:`) and a DOI badge to the README.
