// Unit tests for the transform layer's non-base-edition fan-out (Core v0.3.0).
// Run with `npm test` (node --test). These cover the rank-derivation contract that
// the shipped 1954 slice does not yet exercise (rankOverride, the fail-closed paths),
// so the guarantee holds before the full dataset relies on it.

import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
  transformSanctorale,
  transformSanctoraleEdition,
  deriveCumNostra1955,
  transformOctaves,
  transformPrecedence,
  transformDiscipline,
  checkCoPlacement,
} from '../src/transform.mjs';

const DA = 'roman-divino-afflatu';

// The four sanctoral vigils Cum nostra retained (Title II.9); the derive's count guard
// requires exactly these, so a focused vigil test must include all four.
const RETAINED_VIGIL_IDS = [
  'roman:sanctorale:assumptio:vigilia',
  'roman:sanctorale:ioannes-baptista:vigilia',
  'roman:sanctorale:petrus-paulus:vigilia',
  'roman:sanctorale:laurentius:vigilia',
];

function daBlock(legacyRank, extra = {}) {
  return {
    legacyRank,
    colour: { base: 'white' },
    month: 1,
    day: 1,
    cites: { colour: 'ordo-1954', legacyRank: 'ordo-1954' },
    ...extra,
  };
}

function retainedVigilEntries() {
  return RETAINED_VIGIL_IDS.map((id) => ({
    id,
    kind: 'vigil',
    [DA]: daBlock('vigilia', { vigilOf: id.replace(':vigilia', '') }),
  }));
}

function derived1955(entries) {
  const out = deriveCumNostra1955(entries);
  const byId = new Map(out.map((e) => [e.id, e]));
  return (id) => (byId.get(id) || {})['roman-rubricae-1955'];
}

test('checkCoPlacement passes when every vigilOf/octaveOf target is placed in its edition', () => {
  assert.doesNotThrow(() => checkCoPlacement([
    {
      dir: DA,
      placement: [
        { id: 'roman:sanctorale:matthias' },
        { id: 'roman:sanctorale:matthias:vigilia', vigilOf: 'roman:sanctorale:matthias' },
        { id: 'roman:sanctorale:assumptio' },
        { id: 'roman:sanctorale:assumptio:in-octava', octaveOf: 'roman:sanctorale:assumptio' },
      ],
    },
  ]));
});

test('checkCoPlacement fails closed when a vigil names a feast absent from its edition', () => {
  assert.throws(
    () => checkCoPlacement([
      {
        dir: DA,
        placement: [{ id: 'roman:sanctorale:iacobus:vigilia', vigilOf: 'roman:sanctorale:iacobus' }],
      },
    ]),
    /co-placement violation.*iacobus:vigilia.*vigilOf/,
  );
});

test('transformPrecedence fans an edition table into sorted tiers and both rule variants', () => {
  const { tiers, rules } = transformPrecedence(
    {
      tiers: [
        { selector: 'double', line: 16, ordinal: 16, subOrder: 0, cite: 'rg-da' },
        { selector: 'greatest', line: 2, ordinal: 2, subOrder: 0, cite: 'rg-da' },
      ],
      membership: [{ name: 'greatest', ids: ['roman:temporale:paschal:easter'], cite: 'rg-da' }],
      commemorationLimits: [{ dayClass: 1, limit: 3, cite: 'rg-da' }],
    },
    DA,
  );

  // Tiers are sorted by ordinal (lower wins first), independent of author order.
  assert.deepEqual(
    tiers.map((t) => t.selector),
    ['greatest', 'double'],
  );
  // Both precedence-rules variants are emitted, each tagged with its rule kind.
  const kinds = rules.map((r) => r.rule).sort();
  assert.deepEqual(kinds, ['commemoration-limit', 'membership']);
  const membership = rules.find((r) => r.rule === 'membership');
  assert.deepEqual(membership.ids, ['roman:temporale:paschal:easter']);
});

test('transformDiscipline sorts rules by name and its vigil list for a byte-stable build', () => {
  const { key, meta, rules } = transformDiscipline(
    {
      urn: 'roman:cic-1917',
      name: '1917 Code of Canon Law',
      cite: 'cic-1917',
      rules: [
        { rule: 'friday', fast: false, abstinence: 'full', cite: 'cic-1917:c1252' },
        {
          rule: 'vigil',
          fast: true,
          abstinence: 'full',
          cite: 'cic-1917:c1252',
          vigils: ['roman:temporale:christmas:vigil', 'roman:sanctorale:assumptio:vigilia'],
        },
        { rule: 'ash-wednesday', fast: true, abstinence: 'full', cite: 'cic-1917:c1252' },
      ],
    },
    'cic-1917',
  );

  assert.equal(key, 'cic-1917');
  assert.deepEqual(meta, { id: 'cic-1917', urn: 'roman:cic-1917', name: '1917 Code of Canon Law', cite: 'cic-1917' });
  // Rules are sorted by rule name, independent of author order.
  assert.deepEqual(
    rules.map((r) => r.rule),
    ['ash-wednesday', 'friday', 'vigil'],
  );
  // The vigil list is sorted so the row is byte-stable.
  const vigil = rules.find((r) => r.rule === 'vigil');
  assert.deepEqual(vigil.vigils, [
    'roman:sanctorale:assumptio:vigilia',
    'roman:temporale:christmas:vigil',
  ]);
  // A non-vigil rule carries no vigils key.
  assert.equal('vigils' in rules.find((r) => r.rule === 'friday'), false);
});

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
    vigilia: 4,
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

// --- Base-edition fan-out + edition-only entries (notInBaseEdition, #66) ------------

const BASE = 'roman-rubricae-1960';
const BASE_BLOCK = {
  rank: 3,
  colour: 'white',
  month: 5,
  day: 5,
  cites: { rank: 'rg-1960', colour: 'rg-1960', month: 'rg-1960', day: 'rg-1960' },
};

test('the base pass emits identity, attributes, and placement for a base entry', () => {
  const { identity, attributes, placement } = transformSanctorale(
    [{ id: 'roman:sanctorale:x', kind: 'feast', titulars: ['x'], names: { la: 'X' }, cites: { 'names.la': 'mr-1920' }, [BASE]: BASE_BLOCK }],
    BASE,
  );

  assert.equal(identity.length, 1);
  assert.equal(attributes.length, 1);
  assert.equal(placement.length, 1);
});

test('an entry absent from the base edition (notInBaseEdition) contributes identity only', () => {
  // A pre-1955 vigil the 1960 reform suppressed: it carries only a 1954 block, but its
  // identity is edition-invariant and must still join the shared union (the 1962 edition
  // simply does not place it).
  const vigil = {
    id: 'roman:sanctorale:andreas:vigilia',
    kind: 'vigil',
    titulars: ['andreas'],
    names: { la: 'In Vigilia S. Andreae Apostoli' },
    cites: { 'names.la': 'mr-1920' },
    notInBaseEdition: true,
    [DA]: { legacyRank: 'simplex', colour: 'violet', month: 11, day: 29, vigilOf: 'roman:sanctorale:andreas', cites: CITED },
  };
  const { identity, attributes, placement } = transformSanctorale([vigil], BASE);

  assert.equal(identity.length, 1);
  assert.equal(identity[0].id, 'roman:sanctorale:andreas:vigilia');
  assert.equal(identity[0].kind, 'vigil');
  // The base edition places nothing for it.
  assert.equal(attributes.length, 0);
  assert.equal(placement.length, 0);
});

test('an entry missing the base block WITHOUT the marker is a fail-closed error', () => {
  const stray = {
    id: 'roman:sanctorale:oops',
    kind: 'feast',
    titulars: ['x'],
    names: { la: 'X' },
    cites: { 'names.la': 'mr-1920' },
    [DA]: { legacyRank: 'simplex', colour: 'white', month: 5, day: 5, cites: CITED },
  };
  assert.throws(() => transformSanctorale([stray], BASE), /has no data for edition "roman-rubricae-1960"/);
});

// --- Octave materialisation (#65) --------------------------------------------------

/** A bearing feast carrying the edition colour + date the octave derives from. */
function feast(id, block) {
  return {
    id,
    kind: 'feast',
    titulars: ['x'],
    names: { la: 'X' },
    cites: { 'names.la': 'mr-1920' },
    [DA]: { colour: block.colour, month: block.month, day: block.day, cites: { colour: 'ordo-1954' } },
  };
}

const OCT_CITES = { class: 'ordo-1954', name: 'mr-1920' };

test('a common octave materialises six days within (semidouble) plus the octave day (greater double)', () => {
  const bearing = feast('roman:sanctorale:assumptio', { colour: 'white', month: 8, day: 15 });
  const { identity, attributes, placement } = transformOctaves(
    [bearing],
    DA,
    [{ bearingFeast: 'roman:sanctorale:assumptio', class: 'common', genitive: 'Assumptionis', cites: OCT_CITES }],
  );

  // Six within-days (dies secunda..septima) + the octave day = 7 observances.
  assert.equal(identity.length, 7);
  assert.equal(attributes.length, 7);
  assert.equal(placement.length, 7);

  const within = attributes.filter((a) => a.legacyRank === 'semiduplex');
  const octaveDay = attributes.filter((a) => a.legacyRank === 'duplex-maius');
  assert.equal(within.length, 6);
  assert.equal(octaveDay.length, 1);
  // Grades derive to their RankClass (semidouble and greater-double both collapse to III).
  assert.ok(within.every((a) => a.rank === 3));
  assert.equal(octaveDay[0].rank, 3);
});

test('the octave day falls on the eighth day and the days within on the 2nd..7th, with month rollover', () => {
  // Nativity of St John Baptist, June 24: octave day is July 1 (24 + 7, crossing the month).
  const bearing = feast('roman:sanctorale:nativitas-ioannis-baptistae', { colour: 'white', month: 6, day: 24 });
  const { identity, placement } = transformOctaves(
    [bearing],
    DA,
    [
      {
        bearingFeast: 'roman:sanctorale:nativitas-ioannis-baptistae',
        class: 'common',
        genitive: 'Nativitatis S. Ioannis Baptistae',
        cites: OCT_CITES,
      },
    ],
  );

  const octaveDay = placement.find((p) => p.id.endsWith(':in-octava'));
  assert.equal(octaveDay.month, 7);
  assert.equal(octaveDay.day, 1);

  // dies secunda = June 25 … dies septima = June 30.
  const second = placement.find((p) => p.id.endsWith(':infra-octavam:2'));
  const seventh = placement.find((p) => p.id.endsWith(':infra-octavam:7'));
  assert.deepEqual([second.month, second.day], [6, 25]);
  assert.deepEqual([seventh.month, seventh.day], [6, 30]);

  // Every octave record links back to the bearing feast, and names decline correctly.
  assert.ok(placement.every((p) => p.octaveOf === 'roman:sanctorale:nativitas-ioannis-baptistae'));
  const octaveDayId = octaveDay.id;
  assert.equal(
    identity.find((i) => i.id === octaveDayId).names.la,
    'In Octava Nativitatis S. Ioannis Baptistae',
  );
  assert.equal(
    identity.find((i) => i.id.endsWith(':infra-octavam:4')).names.la,
    'Dies quarta infra Octavam Nativitatis S. Ioannis Baptistae',
  );
});

test('a simple octave materialises only the octave day (simple), with no days within', () => {
  const bearing = feast('roman:sanctorale:laurentius', { colour: 'red', month: 8, day: 10 });
  const { identity, attributes, placement } = transformOctaves(
    [bearing],
    DA,
    [{ bearingFeast: 'roman:sanctorale:laurentius', class: 'simple', genitive: 'S. Laurentii Martyris', cites: OCT_CITES }],
  );

  assert.equal(identity.length, 1);
  assert.equal(placement.length, 1);
  assert.equal(identity[0].kind, 'octave-day');
  assert.equal(attributes[0].legacyRank, 'simplex');
  assert.equal(attributes[0].rank, 4);
  assert.deepEqual([placement[0].month, placement[0].day], [8, 17]);
});

test('octaveDay:false emits the days within but defers the octave day', () => {
  const bearing = feast('roman:sanctorale:assumptio', { colour: 'white', month: 8, day: 15 });
  const { identity, placement } = transformOctaves(
    [bearing],
    DA,
    [
      {
        bearingFeast: 'roman:sanctorale:assumptio',
        class: 'common',
        genitive: 'Assumptionis',
        octaveDay: false,
        cites: OCT_CITES,
      },
    ],
  );

  // Six days within (Aug 16..21), and NO octave day on Aug 22.
  assert.equal(identity.length, 6);
  assert.ok(identity.every((i) => i.kind === 'within-octave'));
  assert.ok(!placement.some((p) => p.id.endsWith(':in-octava')));
  assert.ok(!placement.some((p) => p.month === 8 && p.day === 22));
});

test('the octave inherits (derives) the bearing feast colour and its citation', () => {
  const bearing = feast('roman:sanctorale:petrus-paulus', { colour: 'red', month: 6, day: 29 });
  const { attributes } = transformOctaves(
    [bearing],
    DA,
    [{ bearingFeast: 'roman:sanctorale:petrus-paulus', class: 'common', genitive: 'Ss. Petri et Pauli', cites: OCT_CITES }],
  );

  assert.ok(attributes.every((a) => a.colour.base === 'red'));
  assert.ok(attributes.every((a) => a.cites.colour === 'ordo-1954'));
  // The title cites the public-domain text; the grade/class facts cite the ordo.
  assert.ok(attributes.every((a) => a.cites.legacyRank === 'ordo-1954' && a.cites.rank === 'ordo-1954'));
});

test('an unknown octave class is a fail-closed error', () => {
  const bearing = feast('roman:sanctorale:x', { colour: 'white', month: 5, day: 5 });
  assert.throws(
    () => transformOctaves([bearing], DA, [{ bearingFeast: 'roman:sanctorale:x', class: 'privileged', genitive: 'X', cites: OCT_CITES }]),
    /unknown class "privileged"/,
  );
});

test('an octave whose bearing feast is absent is a fail-closed error', () => {
  assert.throws(
    () => transformOctaves([], DA, [{ bearingFeast: 'roman:sanctorale:ghost', class: 'common', genitive: 'X', cites: OCT_CITES }]),
    /bearing feast "roman:sanctorale:ghost" is not a sanctoral entry/,
  );
});

test('an octave missing its class/name citation or genitive is a fail-closed error', () => {
  const bearing = feast('roman:sanctorale:x', { colour: 'white', month: 5, day: 5 });
  assert.throws(
    () => transformOctaves([bearing], DA, [{ bearingFeast: 'roman:sanctorale:x', class: 'common', genitive: 'X', cites: { class: 'ordo-1954' } }]),
    /cites must carry both "class" and "name"/,
  );
  assert.throws(
    () => transformOctaves([bearing], DA, [{ bearingFeast: 'roman:sanctorale:x', class: 'common', cites: OCT_CITES }]),
    /a Latin "genitive" title phrase is required/,
  );
});

test('a simple octave with octaveDay:false (which materialises nothing) is a fail-closed error', () => {
  const bearing = feast('roman:sanctorale:x', { colour: 'white', month: 5, day: 5 });
  assert.throws(
    () => transformOctaves([bearing], DA, [{ bearingFeast: 'roman:sanctorale:x', class: 'simple', genitive: 'X', octaveDay: false, cites: OCT_CITES }]),
    /materialises nothing/,
  );
});

test('a late-February octave that would cross the bissextile doubling is a fail-closed error', () => {
  const bearing = feast('roman:sanctorale:x', { colour: 'white', month: 2, day: 24 });
  assert.throws(
    () => transformOctaves([bearing], DA, [{ bearingFeast: 'roman:sanctorale:x', class: 'common', genitive: 'X', cites: OCT_CITES }]),
    /bissextile/,
  );
});

// --- deriveCumNostra1955: the 1955 grade reduction + vigil suppression (#69/#71) ----------
// The reduction itself lives here in the transform, NOT in the PHP engine (whose tierOf only
// maps already-reduced grades), so these are its only direct unit coverage.

test('deriveCumNostra1955 reduces the grade ladder per Cum nostra Title II.20-21', () => {
  const block = derived1955([
    ...retainedVigilEntries(),
    { id: 'roman:sanctorale:probe-semi', kind: 'feast', [DA]: daBlock('semiduplex') },
    { id: 'roman:sanctorale:probe-simple', kind: 'feast', [DA]: daBlock('simplex') },
    { id: 'roman:sanctorale:probe-double', kind: 'feast', [DA]: daBlock('duplex') },
    { id: 'roman:sanctorale:probe-dxi', kind: 'feast', [DA]: daBlock('duplex-i-classis') },
  ]);
  assert.equal(block('roman:sanctorale:probe-semi').legacyRank, 'simplex', 'semidouble -> simple (II.20)');
  assert.equal(block('roman:sanctorale:probe-simple').legacyRank, 'commemoratio', 'simple -> commemoration (II.21)');
  assert.equal(block('roman:sanctorale:probe-double').legacyRank, 'duplex', 'doubles unchanged');
  assert.equal(block('roman:sanctorale:probe-dxi').legacyRank, 'duplex-i-classis', 'first-class doubles unchanged');
  assert.equal(block('roman:sanctorale:probe-semi').cites.legacyRank, 'cn-1955', 'the reduced grade cites the decree');
  assert.equal(block('roman:sanctorale:probe-semi').cites.colour, 'ordo-1954', 'unchanged facts keep their 1954 cite');
});

test('deriveCumNostra1955 suppresses non-retained vigils and keeps the four retained ones', () => {
  const block = derived1955([
    ...retainedVigilEntries(),
    { id: 'roman:sanctorale:matthias:vigilia', kind: 'vigil', [DA]: daBlock('vigilia', { vigilOf: 'roman:sanctorale:matthias' }) },
  ]);
  assert.equal(block('roman:sanctorale:matthias:vigilia'), undefined, 'a suppressed vigil gets no 1955 block');
  assert.ok(block('roman:sanctorale:assumptio:vigilia'), 'a retained vigil keeps its 1955 block');
});

test('deriveCumNostra1955 fails closed when the retained-vigil roster changed', () => {
  assert.throws(() => deriveCumNostra1955(retainedVigilEntries().slice(0, 3)), /retained sanctoral vigils/);
});

test('deriveCumNostra1955 refuses a 1954 rankOverride rather than silently dropping it', () => {
  // The base transform enforces override integrity; the derive must not strip the value while
  // keeping its stale cite (which would ship a default rank under a false citation).
  const entries = [
    ...retainedVigilEntries(),
    {
      id: 'roman:sanctorale:probe-override',
      kind: 'feast',
      [DA]: daBlock('duplex', {
        rankOverride: 1,
        cites: { colour: 'ordo-1954', legacyRank: 'ordo-1954', rankOverride: 'ordo-1954' },
      }),
    },
  ];
  assert.throws(() => deriveCumNostra1955(entries), /rankOverride/);
});

test('deriveCumNostra1955 leaves an entry absent from 1954 untouched', () => {
  const block = derived1955([
    ...retainedVigilEntries(),
    { id: 'roman:sanctorale:probe-1962only', kind: 'feast', 'roman-rubricae-1960': daBlock('duplex') },
  ]);
  assert.equal(block('roman:sanctorale:probe-1962only'), undefined, 'no 1954 block -> no 1955 block');
});
