# Corpus generator

Build-time tooling that turns hand-authored, cited YAML facts into the engine's
committed CC0 corpus. **Node is build-time only** — the runtime engine is PHP and
never runs this generator. The corpus is committed to the repository; it is never
generated on install.

## Use

```sh
cd tools/generator
npm ci            # install pinned dependencies from package-lock.json
npm run build     # regenerate data/corpus/ from facts/
npm run verify    # assert the build is reproducible and matches the committed corpus
```

`npm run build` is the single documented command that produces the corpus.

## How it works

```
facts/*.yaml  →  transform  →  validate (data/corpus/schema, draft 2020-12)
              →  provenance gate  →  canonical serialize
              →  data/corpus/**.ndjson + sources.ndjson + MANIFEST.json + LICENSE.txt
```

- **facts/** — the clean-room boundary. A human asserts each fact together with a
  citation (`cites: { field: source-key }`); the generator never scrapes.
  `facts/sources.yaml` is the source registry every citation resolves into.
  `facts/overlays/<slug>.yaml` describes a particular calendar (e.g. SSPX) as a
  declarative set of add / suppress / rerank operations over the universal sanctoral;
  each compiles to `data/corpus/overlays/<slug>/` (operations.ndjson + overlay.json)
  and is read into the engine by `CorpusOverlayData` (#76). An overlay's rank facts
  cite the particular calendar's authority; an added feast's title still cites a
  public-domain text source, so the same born-cited gate applies.
- **src/canonical.mjs** — byte-stable serialization: keys sorted by code unit,
  compact NDJSON with a trailing LF, no wall clock, no randomness, no floats. The
  corpus version comes from `facts/meta.yaml`, never the clock.
- **src/validate.mjs** — validates every emitted record against the frozen corpus
  schemas (issue #39), so an invalid record fails the build, not review.
- **src/provenance.mjs** — the born-cited gate: the build fails closed if any
  human-readable string lacks a citation, any citation points at an unregistered
  source, or a transcribed title cites a non-public-domain source (clean room).
- **src/verify.mjs** — the reproducibility gate CI runs: two fresh builds must be
  byte-identical (determinism) and must match the committed corpus (freshness).

## Licensing

The generated corpus is uncopyrightable facts, dedicated to the public domain
under **CC0-1.0** (`data/corpus/LICENSE.txt`, and `MANIFEST.json`'s `license`).
`REUSE.toml` records the split machine-verifiably: the engine and this generator
are AGPL-3.0-or-later, the corpus is CC0.

## Scope

The pipeline skeleton (#40), born-cited provenance + CC0 (#44), and the full 1962
sanctoral dataset (#41 — the fixed-date universal calendar, read into the engine
by `CorpusSanctoralData`) are in place. The temporal definitions (#42) and
precedence table (#43) build on the same shapes. Particular-calendar overlays (#76)
add the `facts/overlays/` layer over that base, starting with the SSPX calendar.
