// Unit tests for release packaging (#239): datasetName + prepareRelease. Run with
// `npm test`. prepareRelease reads the committed corpus (verified elsewhere) and
// emits the release metadata and SHA256SUMS a release attaches.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { datasetName, prepareRelease } from '../src/package.mjs';
import { DEFAULT_OUT } from '../src/build.mjs';

test('datasetName stamps the corpus version into the archive name', () => {
  assert.equal(datasetName('1962-2026-07-07.3'), 'directorium-dataset-1962-2026-07-07.3');
});

test('prepareRelease packages the committed corpus with a SHA256SUMS manifest', () => {
  const dist = mkdtempSync(join(tmpdir(), 'package-test-'));
  try {
    const result = prepareRelease(dist);

    assert.equal(result.name, datasetName(result.corpusVersion));
    assert.ok(result.fileCount > 0);

    // SHA256SUMS exists and has one line per packaged file.
    const sumsPath = join(dist, 'SHA256SUMS');
    assert.ok(existsSync(sumsPath));
    const lines = readFileSync(sumsPath, 'utf8').trimEnd().split('\n');
    assert.equal(lines.length, result.fileCount);
    for (const line of lines) {
      assert.match(line, /^[0-9a-f]{64} {2}\S/);
    }

    // The manifest and licence are part of the packaged set.
    assert.ok(lines.some((l) => l.endsWith('  MANIFEST.json')));
    assert.ok(lines.some((l) => l.endsWith('  LICENSE.txt')));

    // SHA256SUMS covers the WHOLE tarball, including the schema files the manifest
    // does not itself list (so the documented `sha256sum -c` verifies every file).
    assert.ok(lines.some((l) => l.includes('  schema/')), 'schema files must be covered by SHA256SUMS');

    // release-metadata.json records the version and licence.
    const meta = JSON.parse(readFileSync(join(dist, 'release-metadata.json'), 'utf8'));
    assert.equal(meta.corpusVersion, result.corpusVersion);
    assert.equal(meta.license, 'CC0-1.0');
    assert.equal(meta.fileCount, result.fileCount);
  } finally {
    rmSync(dist, { recursive: true, force: true });
  }
});

test('prepareRelease refuses a corpus that does not match its manifest', () => {
  const corpus = mkdtempSync(join(tmpdir(), 'package-bad-corpus-'));
  writeFileSync(join(corpus, 'a.ndjson'), 'alpha\n');
  writeFileSync(
    join(corpus, 'MANIFEST.json'),
    JSON.stringify({ corpusVersion: 'test-1', files: { 'a.ndjson': { sha256: 'deadbeef' } } }),
  );
  const dist = mkdtempSync(join(tmpdir(), 'package-bad-dist-'));
  try {
    assert.throws(() => prepareRelease(dist, corpus), /does not match its manifest/);
  } finally {
    rmSync(corpus, { recursive: true, force: true });
    rmSync(dist, { recursive: true, force: true });
  }
});
