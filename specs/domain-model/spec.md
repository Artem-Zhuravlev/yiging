# SPEC-002 — I Ching Domain Model

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-14

> Implemented in two passes: **structural** (trigram/hexagram identity, King Wen numbers, line
> mechanics, relationships — verified 2026-08-12) and **classical text** (judgment/image/line
> statements for all 64 hexagrams, from James Legge's 1899 translation — verified 2026-08-14).
> See "Data requirements" for sourcing and [`tasks.md`](tasks.md) for both passes' task history.

## Problem

Every later feature (hexagram explorer, casting engine, readings, journal, AI interpretation)
needs a correct, shared model of the I Ching's actual structure — trigrams, lines, hexagrams,
changing lines, and the classical King Wen sequence. Without a single authoritative model,
each feature would reinvent (and likely mis-implement) hexagram identification and line
mechanics.

## Purpose

Define the pure domain types and relationships that live in `packages/yijing-core`, so the
platform is built on real I Ching structure rather than treated as a generic "random fortune"
generator.

## Scope

- `Trigram` value object (8 trigrams)
- `Line` value object (yin/yang, changing/non-changing, position)
- `Hexagram` value object (64 hexagrams, King Wen sequence, upper/lower trigram, judgment,
  image, line statements)
- Derivation rules: six lines → trigrams → hexagram identity; changing lines → resulting
  hexagram
- Classical relationships: nuclear hexagram, opposite (inverted), complement (all lines
  flipped)
- Static reference data for all 8 trigrams and 64 hexagrams (classical texts, King Wen numbers)

## Out of scope

- Casting methods (three-coin, yarrow stalk) that *produce* line values — that's SPEC-004
  (Casting Engine). This spec only covers what happens once six line values already exist.
- Persistence (readings, journal) — SPEC-005, SPEC-006.
- HTTP API surface for browsing hexagrams — SPEC-003 (Hexagram Explorer).
- AI interpretation — SPEC-008.
- Translation/localization of classical texts beyond the initial source chosen for seed data.

## User behavior

`yijing-core` has no UI or HTTP surface of its own — it is consumed by `apps/api` (SPEC-003+)
and, indirectly, by `apps/web`. "User behavior" here means the API contract other code relies
on:

```
Hexagram::fromLines([yang, yin, yang, yang, yin, yin])
  → Hexagram instance with correct King Wen number, upper/lower trigram, judgment, image

Hexagram::changeLine(position: 2)
  → new Hexagram reflecting that line flipped

Casting::fromCoins(...)              // defined in SPEC-004, consumes this model
  → six Line values (some changing) → primary Hexagram + resulting Hexagram
```

## Functional requirements

### Trigram

- **REQ-TRI-001** — A `Trigram` MUST be constructible from exactly three `Line` values
  (bottom to top) and MUST expose its identity (one of the 8 trigrams).
- **REQ-TRI-002** — Each of the 8 trigrams MUST expose: id, English name, Chinese name (漢字),
  pinyin, symbol (☰ etc.), its three-line structure, and its classical attributes (element,
  family member, direction, natural image).
- **REQ-TRI-003** — Trigram identity MUST be derived purely from its three line values — no
  trigram may be constructed in an invalid (non-8) configuration.

### Line

- **REQ-LN-001** — A `Line` MUST record its position (1–6, bottom to top), its polarity
  (yin/yang), and whether it is changing.
- **REQ-LN-002** — A changing yang line becomes yin (and vice versa) when the line is "changed"
  — this MUST be a pure, deterministic transformation with no randomness in this package.

### Hexagram

- **REQ-HX-001** — A `Hexagram` MUST be constructible from exactly six `Line` values (bottom to
  top) via `Hexagram::fromLines()`.
- **REQ-HX-002** — Given six line values, the resulting hexagram's King Wen number MUST be
  correctly and deterministically identified (all 64 combinations covered).
- **REQ-HX-003** — Each of the 64 hexagrams MUST expose: King Wen number (1–64), Chinese name,
  pinyin, its six-line structure, upper trigram, lower trigram, judgment text, image text, and
  the six individual line statements — all non-nullable `string`/`list<string>` (not nullable
  placeholders; see "Data requirements" for how this was reached in two passes).
- **REQ-HX-004** — `Hexagram::getUpperTrigram()` and `Hexagram::getLowerTrigram()` MUST return
  the correct `Trigram` derived from lines 4–6 and 1–3 respectively.
- **REQ-HX-005** — `Hexagram::changeLine(position)` MUST return a new `Hexagram` with the line
  at that position flipped, leaving the original instance unmodified (immutable value object).
- **REQ-HX-006** — Given a primary hexagram and a set of changing-line positions,
  `Hexagram::getResultingHexagram()` MUST return the hexagram obtained by flipping all changing
  lines at once.
- **REQ-HX-007** — If there are no changing lines, the resulting hexagram MUST be identical to
  the primary hexagram (same King Wen number).
- **REQ-HX-008** — `Hexagram::fromKingWenNumber(int $kingWenNumber)` MUST return the hexagram
  for that number with all 6 lines non-changing (structural identity only — casting-specific
  changing-line state is layered on separately by callers that need it, e.g. SPEC-005's
  repository). MUST throw for a number outside 1–64. Added so consumers that only have a King
  Wen number (a persisted `Consultation`, the Hexagram Explorer) don't each re-derive lines
  from `HexagramCatalog`'s pattern themselves — before this, `apps/api`'s
  `SqliteConsultationRepository` had a private copy of exactly this logic.

### Relationships

- **REQ-REL-001** — `YijingRelations::getNuclearHexagram()` MUST derive the nuclear
  (互卦/hùguà) hexagram from lines 2–4 (as new lower trigram) and 3–5 (as new upper trigram) of
  a given hexagram.
- **REQ-REL-002** — `YijingRelations::getOpposite()` MUST return the hexagram formed by
  inverting the line order (turning the hexagram upside down: line 1↔6, 2↔5, 3↔4).
- **REQ-REL-003** — `YijingRelations::getComplement()` MUST return the hexagram formed by
  flipping every line's polarity (錯卦/cuòguà).

## Non-functional requirements

- **REQ-DM-001** — `packages/yijing-core` MUST have zero dependencies beyond PHP itself (no
  Vue, no PHP framework, no database, no HTTP, no filesystem, no AI) — enforced by
  `composer.json` `require` staying empty of anything but `php`.
- **REQ-DM-002** — All hexagram/trigram derivation logic MUST be pure functions/methods:
  identical inputs always produce identical outputs, no I/O, no randomness.
- **REQ-DM-003** — Reference data for all 8 trigrams and 64 hexagrams (structural fields: id,
  names, King Wen number, six-line structure, trigram composition) MUST be complete before this
  spec can move to `in-progress`. Full completion including classical text MUST be reached
  before this spec can move to `verified` — partial data is not acceptable there, since
  consumers cannot distinguish "not yet seeded" from "genuinely has no line statements." (Met:
  all 64 hexagrams have complete judgment/image/6-line-statement text, non-nullable.)

## Data requirements

Static, versioned-in-code reference data (not a database concern — this package has no
filesystem/DB access per REQ-DM-001):

- 8 trigrams: id, name, Chinese name, pinyin, symbol, lines, attributes.
- 64 hexagrams: King Wen number, Chinese name, pinyin, six lines, upper/lower trigram
  reference, judgment, image, 6 line statements.

**Resolved and done:** classical text (judgment, image, line statements) uses James Legge's
1899 translation — public domain (copyright expired), the standard source used by most
open-source I Ching software. `sacred-texts.com` (the mirror originally proposed here) blocks
automated fetching (HTTP 403); the text was instead transcribed from baharna.com's Legge
edition (a public-domain digitization that cleanly separates Legge's own translation from
other translators' comparison text and Legge's footnote commentary via clear section headings
— "Thwan, or Overall Judgment", "Great Symbolism", "Line Statements") and cross-checked against
a second, independent public-domain source (the Chinese Text Project, ctext.org) on a sample
spanning the set (hexagrams 1, 2, 3, 11, 12, 25, 29, 30, 41, 64) — exact match on every phrase
compared. Fetched and parsed programmatically (not routed through a summarizing/paraphrasing
model, to guarantee verbatim transcription), with an automated completeness scan (no hexagram
under 15 characters per field, no leaked bracket/footnote content, exactly 6 line statements
each) finding zero anomalies across all 64.

**Phasing (complete):** the structural fields (King Wen number, Chinese name, pinyin, six-line
structure, upper/lower trigram) were implemented first, with full test coverage, since they
were the safety-critical, testable part of this spec. Judgment/image/line-statement text for
all 64 hexagrams was populated in this follow-up pass, in `Data/HexagramTextCatalog.php`
(structural identity in `HexagramCatalog` stays separate from descriptive text, per this
project's "don't mix canonical text with structural identity" principle). `Hexagram`'s
judgment/image/lineStatements fields are no longer nullable — `Hexagram::fromLines()` now
requires `HexagramTextCatalog::textFor()` to succeed for every construction, since "not yet
populated" is no longer a state that can occur.

## API requirements

None — this package is framework-free and has no HTTP surface. SPEC-003 (Hexagram Explorer)
will define the `GET /api/hexagrams` / `GET /api/hexagrams/{id}` contract that wraps this model.

## Edge cases

- All six lines identical (e.g. all yang) → still exactly one valid King Wen number (Hexagram 1,
  乾) — model must not special-case "uniform" hexagrams differently.
- All six lines changing → resulting hexagram is the complement of the primary hexagram; this
  should fall out of REQ-HX-006 without special-casing.
- Zero changing lines → resulting hexagram equals primary hexagram (REQ-HX-007) — reading
  interpretation (SPEC-005) decides what "no change" means for the user, not this package.
- Invalid line count (not exactly 3 for `Trigram` / 6 for `Hexagram`) → construction must fail
  loudly (exception), never silently truncate or pad.

## Acceptance criteria

Structural pass (required for `in-progress`):

- [x] All 8 trigrams and all 64 hexagrams are represented with complete structural reference
      data (id, names, King Wen number, six-line structure, trigram composition).
- [x] `Hexagram::fromLines()` correctly identifies all 64 possible six-line combinations
      (property/table-tested, not just a handful of examples).
- [x] `Hexagram::getUpperTrigram()` / `getLowerTrigram()` are correct for all 64 hexagrams.
- [x] `Hexagram::changeLine()` and `getResultingHexagram()` are correct, including the
      zero-changing-lines and all-six-changing edge cases.
- [x] `YijingRelations::getNuclearHexagram()`, `getOpposite()`, `getComplement()` are correct
      against known reference values (cross-checked against a published source).
- [x] `packages/yijing-core`'s `composer.json` has no runtime dependency beyond `php`.
- [x] Test coverage in `yijing-core` is the highest of any package in the repo (per bootstrap
      rule: "the domain core must be heavily tested before UI work expands") — 46 tests, 244
      assertions, versus 2 in `apps/api` and 1 (smoke) in `apps/web`.
- [x] `Hexagram::fromKingWenNumber()` (REQ-HX-008, added post-hoc while building SPEC-003/006)
      returns the correct structural hexagram for all 64 numbers and throws for out-of-range
      input.

Classical text pass (required for `verified`):

- [x] Judgment, image, and all six line statements are populated for all 64 hexagrams from
      Legge's 1899 translation, accurately transcribed (not paraphrased) from a public-domain
      source.
- [x] Transcription cross-checked against a second, independent public-domain source on a
      sample spanning the set — exact match, no paraphrase drift.
- [x] Automated completeness scan across all 64 hexagrams finds zero anomalies (no
      too-short fields, no leaked non-Legge bracket/footnote content, exactly 6 line
      statements each).
- [x] `Hexagram`'s `judgment`/`image`/`lineStatements` are non-nullable — the "not yet
      populated" state this spec explicitly guarded against (REQ-DM-003) no longer exists as
      a possible value.
- [x] `packages/yijing-core` test count: 51 tests, 1530 assertions (up from 46/244 at the
      structural-only pass).

`Data/HexagramTextCatalog.php` implements this — `Hexagram::fromLines()`/`fromKingWenNumber()`
populate `judgment`/`image`/`lineStatements` for every construction now. `apps/api`'s
`HexagramController` and the frontend's `HexagramDetailPage`/`ConsultationPage` (SPEC-003,
SPEC-007, SPEC-009) needed no code changes — they already read these fields; their previous
`null`-handling fallback paths simply stopped triggering. Manually verified end to end: the
live API now serves real Legge text, and the browser UI renders it instead of the "Not yet
available." placeholder.
