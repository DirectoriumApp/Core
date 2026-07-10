// Unit tests for the manifest-integrity check (#238): verifyManifest confirms a
// corpus MANIFEST.json's sha256s match the files on disk. Run with `npm test`.

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { verifyManifest } from '../src/manifest.mjs';
import { sha256 } from '../src/canonical.mjs';
import { DEFAULT_OUT } from '../src/build.mjs';

/** Write a synthetic corpus with the given files and an optional manifest override. */
function synthCorpus(files, manifestFilesOverride) {
  const root = mkdtempSync(join(tmpdir(), 'manifest-test-'));
  for (const [rel, text] of Object.entries(files)) {
    writeFileSync(join(root, rel), text);
  }
  const manifestFiles = manifestFilesOverride
    ?? Object.fromEntries(Object.entries(files).map(([rel, text]) => [rel, { sha256: sha256(text) }]));
  writeFileSync(join(root, 'MANIFEST.json'), JSON.stringify({ corpusVersion: 'test-1', files: manifestFiles }));
  return root;
}

test('the committed corpus matches its own manifest', () => {
  assert.deepEqual(verifyManifest(DEFAULT_OUT), []);
});

test('a correct synthetic manifest reports no problems', () => {
  const root = synthCorpus({ 'a.ndjson': 'alpha\n', 'b.json': '{"x":1}\n' });
  try {
    assert.deepEqual(verifyManifest(root), []);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('a wrong sha256 is reported', () => {
  const root = synthCorpus(
    { 'a.ndjson': 'alpha\n' },
    { 'a.ndjson': { sha256: 'deadbeef' } },
  );
  try {
    const problems = verifyManifest(root);
    assert.equal(problems.length, 1);
    assert.match(problems[0], /a\.ndjson: sha256 .* does not match manifest deadbeef/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('a file listed in the manifest but missing on disk is reported', () => {
  const root = synthCorpus(
    { 'a.ndjson': 'alpha\n' },
    { 'a.ndjson': { sha256: sha256('alpha\n') }, 'gone.ndjson': { sha256: 'abc' } },
  );
  try {
    const problems = verifyManifest(root);
    assert.equal(problems.length, 1);
    assert.match(problems[0], /gone\.ndjson: listed in the manifest but missing on disk/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('a missing MANIFEST.json is reported', () => {
  const root = mkdtempSync(join(tmpdir(), 'manifest-test-'));
  try {
    const problems = verifyManifest(root);
    assert.equal(problems.length, 1);
    assert.match(problems[0], /MANIFEST\.json: missing/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
