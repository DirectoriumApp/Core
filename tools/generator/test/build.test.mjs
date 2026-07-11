// Integration tests for the corpus build pipeline (Core v1.1, #108). These build the
// REAL cited facts to a throwaway directory and assert the whole-vs-diff edition rule
// for the temporal overlay: a WHOLE edition (the Novus Ordo) carries its OWN temporal
// archetypes only, while a DIFF edition (1954) still unions the base 1962 archetypes.
// Run with `npm test` (node --test).

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { build } from '../src/build.mjs';

/** Build the real corpus into a fresh temp dir; return its path (caller cleans up). */
function buildToTemp() {
  const out = mkdtempSync(join(tmpdir(), 'corpus-build-'));
  build(out);
  return out;
}

/** The set of `archetype` keys in an edition's attributes.temporale.ndjson (empty if absent). */
function temporaleArchetypes(out, dir) {
  const path = join(out, 'editions', dir, 'attributes.temporale.ndjson');
  if (!existsSync(path)) {
    return new Set();
  }
  const text = readFileSync(path, 'utf8').trim();
  if (text === '') {
    return new Set();
  }
  return new Set(text.split('\n').map((line) => JSON.parse(line).archetype));
}

test('a whole edition (Novus Ordo) carries only its own temporal archetypes', () => {
  const out = buildToTemp();
  try {
    const no = temporaleArchetypes(out, 'roman-novus-ordo-2002');
    // Its own Ordinary-Time overlay is present …
    assert.ok(no.has('ot-sunday'), 'NO temporale should carry ot-sunday');
    assert.ok(no.has('ot-feria'), 'NO temporale should carry ot-feria');
    // … and NONE of the 1962 base archetypes leak in (no Septuagesima, no Advent-Sunday-I):
    assert.ok(!no.has('septuagesima-sunday'), 'NO must not inherit the 1962 Septuagesima archetype');
    assert.ok(!no.has('advent-sunday-i'), 'NO must not inherit the 1962 Advent archetypes');
    assert.ok(!no.has('passion-sunday'), 'NO has no distinct Passiontide');
  } finally {
    rmSync(out, { recursive: true, force: true });
  }
});

test('a diff edition (1954) still unions the base 1962 temporal archetypes', () => {
  const out = buildToTemp();
  try {
    const da = temporaleArchetypes(out, 'roman-divino-afflatu');
    // The additive octave model: 1954 keeps every 1962 archetype and appends its own extras.
    assert.ok(da.has('advent-sunday-i'), '1954 must inherit the base 1962 archetypes');
    assert.ok(da.has('septuagesima-sunday'), '1954 keeps Septuagesima');
    assert.ok(da.has('epiphany-within-octave'), '1954 appends its own privileged-octave archetypes');
  } finally {
    rmSync(out, { recursive: true, force: true });
  }
});

test('a whole edition authored temporal-first emits no empty sanctoral files', () => {
  const out = buildToTemp();
  try {
    const dir = join(out, 'editions', 'roman-novus-ordo-2002');
    assert.ok(existsSync(join(dir, 'attributes.temporale.ndjson')), 'NO temporale is emitted');
    // No empty sanctoral shapes are committed for the temporal-first slice.
    assert.ok(!existsSync(join(dir, 'attributes.sanctorale.ndjson')), 'no empty NO sanctoral attributes');
    assert.ok(!existsSync(join(dir, 'placement.sanctorale.ndjson')), 'no empty NO sanctoral placement');
  } finally {
    rmSync(out, { recursive: true, force: true });
  }
});
