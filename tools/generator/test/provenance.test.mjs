// Unit tests for the born-cited provenance gate — in particular the Core option A
// exception (#108): a generic name-DESCRIPTOR (a temporal day-label OR a sanctoral
// saint's proper name + grade word / reform-coined feast title) may cite a `reference`
// source, a transcribed literary title must cite a public-domain text, and an oracle is
// never a name source in either case. Run with `npm test` (node --test).

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { checkProvenance } from '../src/provenance.mjs';

const SOURCES = [
  { key: 'mr-1920', kind: 'text', rights: 'public-domain', urn: 'x', title: 'Missale Romanum 1920' },
  { key: 'nu-1969', kind: 'reference', rights: 'reference', urn: 'x', title: 'Normae universales 1969' },
  { key: 'romcal', kind: 'oracle', rights: 'oracle', urn: 'x', title: 'romcal' },
];

/** A temporal identity row naming its label with the given cite source. */
function temporal(cite) {
  return { shape: 'identity.temporale', records: [{ archetype: 'ot-sunday', names: { la: 'Dominica per annum' }, cites: { 'names.la': cite } }] };
}

/** A sanctoral identity row naming its title with the given cite source. */
function sanctoral(cite) {
  return { shape: 'identity.sanctorale', records: [{ id: 'roman:sanctorale:x', names: { la: 'Sanctus X' }, cites: { 'names.la': cite } }] };
}

test('a temporal label may cite a reference source (option A)', () => {
  const { problems } = checkProvenance([temporal('nu-1969')], SOURCES);
  assert.deepEqual(problems, []);
});

test('a temporal label may still cite a public-domain text', () => {
  const { problems } = checkProvenance([temporal('mr-1920')], SOURCES);
  assert.deepEqual(problems, []);
});

test('a temporal label may NOT cite an oracle source', () => {
  const { problems } = checkProvenance([temporal('romcal')], SOURCES);
  assert.equal(problems.length, 1);
  assert.match(problems[0], /public-domain text or a reference source/);
});

test('a sanctoral name-descriptor may cite a reference source (option A extended, #108)', () => {
  // A reformed-calendar saint canonised after the public-domain sources close, or a reform-coined
  // feast title, names an uncopyrightable descriptor cited to the norming General Roman Calendar.
  const { problems } = checkProvenance([sanctoral('nu-1969')], SOURCES);
  assert.deepEqual(problems, []);
});

test('a sanctoral name may still cite a public-domain text', () => {
  const { problems } = checkProvenance([sanctoral('mr-1920')], SOURCES);
  assert.deepEqual(problems, []);
});

test('a sanctoral name may NOT cite an oracle source', () => {
  const { problems } = checkProvenance([sanctoral('romcal')], SOURCES);
  assert.equal(problems.length, 1);
  assert.match(problems[0], /public-domain text or a reference source/);
});

test('an unknown source key is still a problem for a temporal label', () => {
  const { problems } = checkProvenance([temporal('not-a-source')], SOURCES);
  assert.equal(problems.length, 1);
  assert.match(problems[0], /cites unknown source/);
});

test('a temporal fact (rank) may cite a reference source without triggering the name rule', () => {
  const shapes = [{
    shape: 'attributes.temporale',
    records: [{ archetype: 'ot-sunday', rank: 2, cites: { rank: 'nu-1969', colour: 'nu-1969' } }],
  }];
  const { problems } = checkProvenance(shapes, SOURCES);
  assert.deepEqual(problems, []);
});
