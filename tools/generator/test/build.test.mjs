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
    // Its own Ordinary-Time and Advent/Christmas overlays are present …
    assert.ok(no.has('ot-sunday'), 'NO temporale should carry ot-sunday');
    assert.ok(no.has('ot-feria'), 'NO temporale should carry ot-feria');
    assert.ok(no.has('no-advent-sunday'), 'NO temporale should carry its own Advent Sunday');
    assert.ok(no.has('no-advent-privileged'), 'NO temporale should carry its date-named late-Advent ferias');
    assert.ok(no.has('no-baptism'), 'NO temporale should carry the Baptism of the Lord');
    // … its own paschal half (Lent, Holy Week, Easter Time) is present too …
    assert.ok(no.has('no-ash-wednesday'), 'NO temporale should carry Ash Wednesday');
    assert.ok(no.has('no-lent-sunday'), 'NO temporale should carry its Lenten Sundays');
    assert.ok(no.has('no-palm-sunday'), 'NO temporale should carry the merged red Palm Sunday');
    assert.ok(no.has('no-easter'), 'NO temporale should carry its re-titled Easter Sunday');
    assert.ok(no.has('no-easter-octave-feria'), 'NO temporale should carry the Octave of Easter');
    assert.ok(no.has('no-christ-the-king'), 'NO temporale should carry Christ the King');
    // … the shared great feasts of the Lord and Holy Thursday are RE-DECLARED here (so they
    // resolve for this edition) even though their identity lives in the shared union …
    assert.ok(no.has('nativity'), 'NO re-declares the shared Nativity so it resolves for this edition');
    assert.ok(no.has('epiphany'), 'NO re-declares the shared Epiphany');
    assert.ok(no.has('ascension'), 'NO re-declares the shared Ascension');
    assert.ok(no.has('pentecost'), 'NO re-declares the shared Pentecost');
    assert.ok(no.has('maundy-thursday'), 'NO re-declares the shared Holy Thursday');
    // … and NONE of the reform-dropped 1962 base archetypes leak in:
    assert.ok(!no.has('septuagesima-sunday'), 'NO must not inherit the 1962 Septuagesima archetype');
    assert.ok(!no.has('advent-sunday-i'), 'NO must not inherit the 1962 Advent archetypes');
    assert.ok(!no.has('advent-feria'), 'NO must not inherit the 1962 "infra Hebdomadam" Advent ferias');
    assert.ok(!no.has('advent-ember'), 'NO dropped the Advent Ember days');
    assert.ok(!no.has('passion-sunday'), 'NO has no distinct Passiontide');
    assert.ok(!no.has('lent-ember'), 'NO dropped the Lenten Ember days');
    assert.ok(!no.has('pentecost-octave-feria'), 'NO dropped the octave of Pentecost');
  } finally {
    rmSync(out, { recursive: true, force: true });
  }
});

test('a shared great feast is deduped into the temporal identity union, not double-listed', () => {
  const out = buildToTemp();
  try {
    const text = readFileSync(join(out, 'identity', 'temporale.ndjson'), 'utf8').trim();
    const rows = text.split('\n').map((line) => JSON.parse(line));
    // The Nativity is re-declared by the Novus Ordo with the base identity (same kind, name, and
    // mr-1920 citation), so the union holds exactly ONE `nativity` row, not one per edition.
    const nativity = rows.filter((r) => r.archetype === 'nativity');
    assert.equal(nativity.length, 1, 'Nativity must appear once in the shared identity union');
    assert.equal(nativity[0].cites['names.la'], 'mr-1920', 'the shared Nativity keeps its public-domain citation');
    const epiphany = rows.filter((r) => r.archetype === 'epiphany');
    assert.equal(epiphany.length, 1, 'Epiphany must appear once in the shared identity union');
    // The paschal-half shared feasts of the Lord and Holy Thursday dedupe the same way: one row
    // each, keeping the public-domain Missal citation the base transcribes.
    for (const key of ['ascension', 'pentecost', 'maundy-thursday']) {
      const shared = rows.filter((r) => r.archetype === key);
      assert.equal(shared.length, 1, `${key} must appear once in the shared identity union`);
      assert.equal(shared[0].cites['names.la'], 'mr-1920', `the shared ${key} keeps its public-domain citation`);
    }
    // Easter Sunday is NOT shared — the reform re-titled it — so it is a distinct row citing the
    // reform's norming document, exactly like every other NO-specific archetype (option A).
    const noEaster = rows.filter((r) => r.archetype === 'no-easter');
    assert.equal(noEaster.length, 1, 'the reform-titled Easter is its own identity row');
    assert.equal(noEaster[0].cites['names.la'], 'nu-1969', 'a reform-titled label cites nu-1969');
    // A NO-specific archetype is its own row, cited to the reform's norming document (option A).
    const noOctave = rows.filter((r) => r.archetype === 'no-octave-day');
    assert.equal(noOctave.length, 1, 'the NO octave day is its own identity row');
    assert.equal(noOctave[0].cites['names.la'], 'nu-1969', 'a reform-specific label cites nu-1969');
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
