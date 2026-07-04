// Unit tests for the transform layer's non-base-edition fan-out (Core v0.3.0).
// Run with `npm test` (node --test). These cover the rank-derivation contract that
// the shipped 1954 slice does not yet exercise (rankOverride, the fail-closed paths),
// so the guarantee holds before the full dataset relies on it.

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { transformSanctoraleEdition } from '../src/transform.mjs';

const DA = 'roman-divino-afflatu';

/** A minimal sanctoral entry carrying one Divino-Afflatu edition block. */
function entry(id, block) {
  return {
    id,
    kind: 'feast',
    titulars: ['x'],
    names: { la: 'X' },
    cites: { 'names.la': 'mr-1920' },
    [DA]: block,
  };
}

const CITED = { legacyRank: 'ordo-1954', colour: 'ordo-1954', month: 'ordo-1954', day: 'ordo-1954' };

test('derives the numeric rank from the legacy grade, cited to the grade source', () => {
  const { attributes } = transformSanctoraleEdition(
    [entry('roman:sanctorale:assumptio', { legacyRank: 'duplex-i-classis', colour: 'white', month: 8, day: 15, cites: CITED })],
    DA,
  );

  assert.equal(attributes.length, 1);
  assert.equal(attributes[0].rank, 1);
  assert.equal(attributes[0].legacyRank, 'duplex-i-classis');
  // The derived class inherits the grade's citation — no second hand-typed rank fact.
  assert.equal(attributes[0].cites.rank, 'ordo-1954');
  assert.equal(attributes[0].cites.legacyRank, 'ordo-1954');
});

test('maps each sanctoral grade to its default class', () => {
  const grades = {
    'duplex-i-classis': 1,
    'duplex-ii-classis': 2,
    'duplex-maius': 3,
    duplex: 3,
    semiduplex: 3,
    simplex: 4,
    commemoratio: 4,
  };
  for (const [grade, expected] of Object.entries(grades)) {
    const { attributes } = transformSanctoraleEdition(
      [entry('roman:sanctorale:x', { legacyRank: grade, colour: 'white', month: 5, day: 5, cites: CITED })],
      DA,
    );
    assert.equal(attributes[0].rank, expected, `grade ${grade}`);
  }
});

test('an explicit rankOverride wins over the default and carries its own citation', () => {
  const { attributes } = transformSanctoraleEdition(
    [
      entry('roman:sanctorale:x', {
        legacyRank: 'simplex',
        rankOverride: 3,
        colour: 'white',
        month: 5,
        day: 5,
        cites: { ...CITED, rankOverride: 'ordo-1954' },
      }),
    ],
    DA,
  );

  assert.equal(attributes[0].rank, 3); // the override, not simplex's default of 4
  assert.equal(attributes[0].legacyRank, 'simplex');
  assert.equal(attributes[0].cites.rank, 'ordo-1954');
});

test('a rankOverride without its own citation is a fail-closed error', () => {
  assert.throws(
    () =>
      transformSanctoraleEdition(
        [entry('roman:sanctorale:x', { legacyRank: 'simplex', rankOverride: 3, colour: 'white', month: 5, day: 5, cites: CITED })],
        DA,
      ),
    /rankOverride must carry cites\.rankOverride/,
  );
});

test('a grade with no default class and no override is a fail-closed error', () => {
  assert.throws(
    () =>
      transformSanctoraleEdition(
        [entry('roman:sanctorale:x', { legacyRank: 'dominica-maior', colour: 'white', month: 5, day: 5, cites: CITED })],
        DA,
      ),
    /no default RankClass/,
  );
});

test('a block missing its legacyRank grade is a fail-closed error', () => {
  assert.throws(
    () =>
      transformSanctoraleEdition(
        [entry('roman:sanctorale:x', { colour: 'white', month: 5, day: 5, cites: { colour: 'ordo-1954', month: 'ordo-1954', day: 'ordo-1954' } })],
        DA,
      ),
    /must declare a legacyRank/,
  );
});

test('entries without a block for the edition are absent from it', () => {
  const { attributes, placement } = transformSanctoraleEdition(
    [{ id: 'roman:sanctorale:only-1960', 'roman-rubricae-1960': { rank: 3, colour: 'white', month: 1, day: 1, cites: {} } }],
    DA,
  );

  assert.equal(attributes.length, 0);
  assert.equal(placement.length, 0);
});

test('placement carries month/day and a vigilOf link', () => {
  const { placement } = transformSanctoraleEdition(
    [
      entry('roman:sanctorale:assumptio:vigilia', {
        legacyRank: 'simplex',
        colour: 'violet',
        month: 8,
        day: 14,
        vigilOf: 'roman:sanctorale:assumptio',
        cites: CITED,
      }),
    ],
    DA,
  );

  assert.equal(placement.length, 1);
  assert.equal(placement[0].month, 8);
  assert.equal(placement[0].vigilOf, 'roman:sanctorale:assumptio');
});
