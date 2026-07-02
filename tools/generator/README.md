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
              →  canonical serialize  →  data/corpus/**.ndjson + MANIFEST.json
```

- **facts/** — the clean-room boundary. A human asserts each fact together with a
  citation (`cites: { field: source-key }`); the generator never scrapes.
- **src/canonical.mjs** — byte-stable serialization: keys sorted by code unit,
  compact NDJSON with a trailing LF, no wall clock, no randomness, no floats. The
  corpus version comes from `facts/meta.yaml`, never the clock.
- **src/validate.mjs** — validates every emitted record against the frozen corpus
  schemas (issue #39), so an invalid record fails the build, not review.
- **src/verify.mjs** — the reproducibility gate CI runs: two fresh builds must be
  byte-identical (determinism) and must match the committed corpus (freshness).

## Scope

This is the pipeline skeleton (issue #40), proven on the sanctoral shapes. The full
1962 sanctoral dataset (#41), temporal definitions (#42), precedence table (#43),
and the born-cited provenance gate + CC0 licensing (#44) build on it.
