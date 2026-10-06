// Tiny deterministic PRNG. Same seed, same output across runs and across platforms.
// Uses sfc32 (small fast counter, 32-bit) seeded from the Mulberry32 of the seed string.
// Zero dependencies.

export function makeRng(seedText: string): () => number {
  let hash = 2166136261;
  for (let i = 0; i < seedText.length; i++) {
    hash ^= seedText.charCodeAt(i);
    hash = Math.imul(hash, 16777619);
  }
  // Four 32-bit state words.
  let a = hash >>> 0;
  let b = (hash ^ 0x9e3779b9) >>> 0;
  let c = (hash ^ 0x243f6a88) >>> 0;
  let d = (hash ^ 0xb7e15162) >>> 0;

  return function next(): number {
    a |= 0;
    b |= 0;
    c |= 0;
    d |= 0;
    const t = (((a + b) | 0) + d) | 0;
    d = (d + 1) | 0;
    a = b ^ (b >>> 9);
    b = (c + (c << 3)) | 0;
    c = ((c << 21) | (c >>> 11)) >>> 0;
    c = (c + t) | 0;
    return (t >>> 0) / 4294967296;
  };
}

export function pick<T>(rng: () => number, items: readonly T[]): T {
  if (items.length === 0) throw new Error('pick: empty array');
  const i = Math.floor(rng() * items.length);
  // Non-null assertion is safe: i ∈ [0, items.length).
  return items[i] as T;
}

export function range(rng: () => number, min: number, max: number): number {
  return min + rng() * (max - min);
}

export function int(rng: () => number, min: number, max: number): number {
  return Math.floor(range(rng, min, max + 1));
}
