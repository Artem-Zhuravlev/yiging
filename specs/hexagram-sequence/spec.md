# SPEC-056 — Sequence of the Hexagrams (序卦傳 / Xù Guà)

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-09-01

## Problem

The app presents the 64 hexagrams in the King Wen order and lets you page through them, but
never says *why* that order is what it is. The classical answer is the 序卦傳 (Xù Guà, the
"Treatise on the Orderly Sequence of the Hexagrams") — one of the Ten Wings — which gives, for
every hexagram from 3 onward, a one-sentence rationale for why it follows the one before it
("Meng is descriptive of what is undeveloped… these require to be nourished; hence Meng is
followed by Xu"). It is short, canonical, already in the public domain in Legge's translation,
and it turns "hexagram 4 comes after hexagram 3" from an arbitrary fact into a reading.

## Purpose

Add the Xù Guà rationale to the hexagram detail page: for each hexagram, the classical sentence
explaining its place in the sequence, sourced from Legge's Appendix VI.

## Scope

### `packages/yijing-core`

- `Yijing\Core\Data\HexagramSequenceCatalog` — `const ENTRIES` keyed by King Wen number
  `3..64`, each value the Xù Guà sentence explaining why that hexagram follows its predecessor
  (hexagram 31 carries the Section II preamble, "Heaven and earth existing…", which introduces
  the second half of the sequence; hexagram 30 carries the extra closing clause of Section I).
  - `public static function precedentFor(int $kingWenNumber): ?string` — the sentence, or
    `null` for hexagrams 1 and 2 (the sequence opens with Qián and Kūn as the heaven/earth
    pair; the Xù Guà offers no "why it follows" for them).
- Pure data, no behaviour. `Hexagram` itself is **not** modified — the controller reads the
  catalog directly, exactly as it does for `HexagramTextCatalog::specialTextFor()` (SPEC-052).

**Source / provenance.** Legge's translation of the 序卦傳, Appendix VI of *The Yî King*
(Sacred Books of the East, vol. XVI, 1899) — public domain (see SPEC-002). Transcribed from the
Chinese Text Project's presentation of Legge's text
(`ctext.org/book-of-changes/xu-gua`), which modernises Legge's hexagram-name romanisation to
pinyin, and cross-checked against the baharna.com Legge digitisation
(`baharna.com/iching/legge/Appendix 6 - Sequence of the Hexagrams.htm`) — wording identical
apart from the romanisation system (baharna keeps Legge's "Khien / Kun / Mang…"). One obvious
OCR artefact in the ctext copy ("Fu. is followed") is normalised to "Fu is followed".

### API

- `GET /api/hexagrams/{id}` gains `sequencePrecedent: string | null` — detail-only, gated by
  the same `includeDynamics` path SPEC-053 uses (`show()` and `fromLines()`); the 64-item
  `GET /api/hexagrams` list is unchanged.

### Frontend

- `entities/hexagram/model.ts`: `Hexagram` gains `sequencePrecedent?: string | null`
  (optional — existing fixtures untouched, as with `lineDynamics`).
- `HexagramDetailPage.vue`: a "Place in the sequence" section (shown only when
  `sequencePrecedent` is non-null) with a heading naming the predecessor
  ("Why 4. 蒙 (Méng) follows 3. 屯 (Zhūn)") that links to hexagram N−1, the classical
  sentence, and a one-line note crediting the 序卦傳.
- Localised (en + uk): the section title, the heading template, and the source note. The
  quoted classical sentence itself is English-only (it is a quotation from Legge, like the
  Judgment / Image / line texts already on the page).

## Out of scope

- **The 雜卦傳 (Zá Guà, "hexagrams in irregular order")** — the other sequence-related Wing.
  Separate spec if wanted.
- **Original Chinese text + pinyin for the Judgments / line statements** — the other "deeper
  source material" candidate; its own (much larger) transcription effort.
- **Prev/next sequence navigation buttons** beyond the single "← predecessor" link in the new
  section (the hexagram list and `?` paging already move between hexagrams).
- **Feeding the precedent into the AI interpretation prompt** or showing it on the consultation
  page — natural follow-ups, not here.
- Any change to the hexagram list payload, or to `Hexagram`.

## Functional requirements

- **REQ-HS-001** — `HexagramSequenceCatalog::precedentFor($n)` returns the Xù Guà sentence for
  every `$n` in `3..64`, and `null` for `1` and `2`.
- **REQ-HS-002** — The sentence for a hexagram names that hexagram and (for `3..30`, `32..64`)
  its predecessor, matching Legge's Appendix VI as presented by ctext.org (pinyin names).
- **REQ-HS-003** — `GET /api/hexagrams/{id}` and `GET /api/hexagrams/from-lines` include
  `sequencePrecedent`; `GET /api/hexagrams` does not.
- **REQ-HS-004** — The hexagram detail page shows the precedent sentence and a link to the
  predecessor hexagram when one exists, and omits the section for hexagrams 1 and 2.

## Non-functional requirements

- **REQ-HS-020** — The catalog is pure and unit-tested in `yijing-core` (1 & 2 → null; a
  Section I entry, the Section II preamble on 31, and the closing entry on 64 have the expected
  content; every 3..64 present).
- **REQ-HS-021** — `phpstan` level 8 + `php-cs-fixer` clean in `yijing-core` and `apps/api`.
- **REQ-HS-022** — New UI strings localised (en + uk); `npm run verify` passes; existing
  hexagram-fixture specs stay green (the model field is optional).

## Data requirements

- 62 short text entries (King Wen 3..64) compiled into `HexagramSequenceCatalog`, sourced as
  described above. No database, no migration.

## API requirements

- `GET /api/hexagrams/{id}` → response gains `sequencePrecedent: string | null`.
- `GET /api/hexagrams/from-lines` → same addition.
- `GET /api/hexagrams` → unchanged.

## Edge cases

- Hexagrams 1 and 2 → `sequencePrecedent` is `null`; the detail-page section is absent.
- Hexagram 31 → the sentence is the Section II preamble (no "is followed by" clause); the
  predecessor link (to 30) is still shown.
- Hexagram 30 → its entry ends with the extra Section I closing clause ("Li denotes being
  attached, or adhering, to.").
- `from-lines` for the pattern of hexagram 1 → `sequencePrecedent` is `null`.

## Acceptance criteria

- [x] `HexagramSequenceCatalog` has an entry for every hexagram 3..64, `null` for 1 and 2, and
      the 31 / 64 special cases read correctly.
- [x] `GET /api/hexagrams/3` → `sequencePrecedent` mentions "Zhun"; `GET /api/hexagrams/1` →
      `null`; `GET /api/hexagrams` items have no `sequencePrecedent` key.
- [x] The hexagram detail page renders a "Place in the sequence" section with the predecessor
      link for hexagram 4, and none for hexagram 1.
- [x] `npm run verify` passes; `phpstan` + `php-cs-fixer` clean.
