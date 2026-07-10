# Releasing

Releases are automated and cut from a green pipeline; the only manual steps are
one-time account setup a maintainer performs once.

## How a release is cut

1. Conventional-Commit PRs land on `develop` and flow to `main`.
2. [`release-please`](../.github/workflows/release-please.yml) opens a **release PR** on
   `main` that bumps the version (and `CITATION.cff` via its extra-file updater) and
   assembles the changelog.
3. Merging that release PR **tags** the version and creates the GitHub release — only
   from a green pipeline, since `main` requires the same checks as every PR.
4. On that release the workflow attaches the **CC0 dataset artifact** (the versioned
   tarball + `SHA256SUMS`, see [citable-dataset.md](citable-dataset.md)).

The engine's public API and output contract are frozen (see
[api-stability.md](api-stability.md)), so a 1.x release is always drop-in for its
predecessors.

## Packaging

`composer.json` carries the complete package metadata (keywords, `homepage`, `authors`,
`support` links, and the `>=7.4` PHP requirement the CI matrix tests on 7.4/8.1/8.2/8.3).
The distributed package is **lean**: [`.gitattributes`](../.gitattributes) `export-ignore`
excludes the tests, tooling, docs, and CI/release config from the Packagist archive, so a
`composer require` pulls only `src/`, the runtime `data/corpus/`, and the licence and
provenance files. Validate locally exactly as CI does:

```bash
composer validate --strict --no-check-publish
```

## One-time maintainer setup

These need an external account and can't be scripted from the repo:

- **Packagist** — submit `https://github.com/DirectoriumApp/Core` at
  <https://packagist.org/packages/submit> and enable the GitHub webhook, so each tag
  auto-updates the `directorium/core` listing. After this, `composer require
  directorium/core` installs the tagged release.
- **Zenodo** — enable the GitHub↔Zenodo integration to mint a per-release DOI (see
  [citable-dataset.md](citable-dataset.md)).

## Cutting 1.0.0

When the platform is ready to commit to the frozen 1.x API, merge the release-please
release PR that targets `1.0.0`. That is the one-way door: after it, breaking changes
require a 2.0. Everything the freeze needs (the snapshot guards, the contract-shape pin,
the semver policy) is already in place, so 1.0.0 is a tag on a green build, not a code
change.
