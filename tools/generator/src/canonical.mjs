// Canonical, byte-stable serialization for the generated corpus.
//
// Reproducibility depends entirely on this module producing identical bytes for
// identical input, on every platform and run: no wall clock, no randomness, no
// environment, no locale, no floats. Object keys are emitted in UTF-16 code-unit
// order (JavaScript's default string comparison), recursively.
//
// The serializer builds the JSON text itself rather than delegating to
// JSON.stringify, because JSON.stringify emits *integer-like* keys in ascending
// numeric order regardless of insertion order — which would silently defeat the
// key sort the moment a numeric-looking key (a year, a day number) appeared. Here
// every key is emitted in the sorted order we choose, and non-integer numbers are
// rejected outright, so the "canonical" guarantee holds for any future record.

import { createHash } from 'node:crypto';

function serializeScalar(value) {
  if (value === null) {
    return 'null';
  }
  const type = typeof value;
  if (type === 'string') {
    return JSON.stringify(value); // correct JSON string escaping, raw UTF-8 otherwise
  }
  if (type === 'boolean') {
    return value ? 'true' : 'false';
  }
  if (type === 'number') {
    if (!Number.isFinite(value)) {
      throw new Error(`non-finite number is not serializable: ${value}`);
    }
    if (!Number.isInteger(value)) {
      throw new Error(`non-integer number is not byte-stable and is forbidden in the corpus: ${value}`);
    }
    return String(value);
  }
  throw new Error(`value of type ${type} is not serializable`);
}

/**
 * Serialize `value` to JSON with keys in code-unit order. `indent` is undefined
 * for compact output or a positive integer for pretty output; `level` is the
 * current nesting depth (internal).
 */
function serialize(value, indent, level) {
  if (value === null || typeof value !== 'object') {
    return serializeScalar(value);
  }
  const newline = indent === undefined ? '' : '\n';
  const inner = indent === undefined ? '' : ' '.repeat(indent * (level + 1));
  const outer = indent === undefined ? '' : ' '.repeat(indent * level);
  const comma = indent === undefined ? ',' : ',' + newline + inner;
  const colon = indent === undefined ? ':' : ': ';

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return '[]';
    }
    const items = value.map((item) => serialize(item, indent, level + 1));
    return '[' + newline + inner + items.join(comma) + newline + outer + ']';
  }

  const keys = Object.keys(value).filter((key) => value[key] !== undefined).sort();
  if (keys.length === 0) {
    return '{}';
  }
  const entries = keys.map(
    (key) => JSON.stringify(key) + colon + serialize(value[key], indent, level + 1),
  );
  return '{' + newline + inner + entries.join(comma) + newline + outer + '}';
}

/** Compact single-line JSON with sorted keys (one NDJSON record). */
export function toCompact(obj) {
  return serialize(obj, undefined, 0);
}

/** Pretty (2-space) JSON with sorted keys and a trailing newline (a singleton file). */
export function toPretty(obj) {
  return serialize(obj, 2, 0) + '\n';
}

/** NDJSON: one canonical record per line, LF-separated, with a trailing newline. */
export function toNdjson(records) {
  if (records.length === 0) {
    return '';
  }
  return records.map(toCompact).join('\n') + '\n';
}

/** Hex SHA-256 of a UTF-8 string (content-addressed, so it is time-independent). */
export function sha256(text) {
  return createHash('sha256').update(text, 'utf8').digest('hex');
}
