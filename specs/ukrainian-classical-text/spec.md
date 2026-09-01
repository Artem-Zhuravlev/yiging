# SPEC-057 — Ukrainian Classical Text

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-09-01

## Problem

The UI chrome is fully localised (EN / UK), but the *classical text the app renders* — the
Judgments, Images, line statements, the Xù Guà sequence sentences, and the "Use Nine / Use Six"
passages — is James Legge's 1899 English, served the same regardless of locale. On a Ukrainian
hexagram or consultation page that means the page frame is Ukrainian and the substance is
English. A locale that is ~20 % translated is not a real locale.

## Purpose

Give the `uk` locale a Ukrainian rendering of the whole classical-text corpus: 64 Judgments,
64 Images, 384 line statements (`HexagramTextCatalog`), 63 Xù Guà sentences
(`HexagramSequenceCatalog`), and the 2 special texts — ~577 passages. Serve it from the API
when the caller asks for `uk`, so every page that shows this text (hexagram detail, hexagram
list/search, compare, consultation, shared consultation) shows it in Ukrainian.

## Scope

### `packages/yijing-core`

- `Yijing\Core\Data\HexagramTextCatalogUk` — `const ENTRIES` in the exact shape of
  `HexagramTextCatalog::ENTRIES` (`array{judgment, image, lineStatements: list<string>}` keyed
  1..64), plus `const SPECIAL` (keys 1, 2). Ukrainian.
- `Yijing\Core\Data\HexagramSequenceCatalogUk` — `const ENTRIES` keyed 3..64. Ukrainian.
- `HexagramTextCatalog::textFor(int, string $locale = 'en')` and
  `::specialTextFor(int, string $locale = 'en')` — return the `…Uk` value when
  `$locale === 'uk'` (falling back to the English entry if a key is somehow absent), the
  English value otherwise. Out-of-range still throws.
- `HexagramSequenceCatalog::precedentFor(int, string $locale = 'en')` — same pattern.
- `Hexagram` is **unchanged** — it stays the structural, English-canonical value object; the
  localized strings are read from the catalogs at the API boundary, not baked into the object.

**Provenance.** The Ukrainian entries are a **machine-assisted translation of Legge's English**
— not a translation from the Chinese, and not a transcription of a published Ukrainian edition
(none in the public domain is known). They exist for readability of the `uk` locale. The
English `ENTRIES` remain the sourced, twice-cross-checked canonical text (SPEC-002); the
`…Uk` classes carry a docblock stating this explicitly. This is a deliberate, user-approved
exception to SPEC-002's two-source rule, limited to the `uk` text.

### API

- A `?lang=` query parameter (`en` | `uk`, anything else → `en`) on the read endpoints that
  return classical text:
  - `GET /api/hexagrams`, `GET /api/hexagrams/{id}`, `GET /api/hexagrams/from-lines`,
    `GET /api/hexagrams/compare` — `judgment` / `image` / `lineStatements` /
    `sequencePrecedent` come from the catalog in the requested locale.
  - `GET /api/consultations/{id}` — `readingGuidance.refs[].text` and
    `readingGuidance.specialTextContent` are resolved from the catalog in the requested
    locale (they are currently pulled from the English-baked `Hexagram`).
- `App\Core\RequestLocale::from(Request): string` — the one place that reads and validates
  `?lang`.
- No response-shape change; only the language of the text fields.

### Frontend

- `fetchHexagram`, `fetchHexagrams`, `compareHexagrams`, `fetchConsultation` gain an optional
  `lang` argument; when it is not `'en'` they append `?lang=<lang>`.
- The pages that render classical text pass `useI18n().locale.value` and **re-fetch when the
  locale changes** (a `watch` on `locale`): `HexagramDetailPage`, `HexagramListPage`,
  `HexagramComparePage`, `ConsultationPage`, `SharedConsultationPage`. Callers that only use a
  hexagram's structure (`CastingReveal`, the consultation-page line diagrams, the home
  hexagram-of-the-day card) may pass the locale but do not need the re-fetch.
- No new UI strings; the `hexagramDetail.sourceSuffix` note already present is extended (EN +
  UK) to say, for `uk`, that the classical text is a translation of Legge's English.

## Out of scope

- **Trigram names / attributes, hexagram Chinese names, pinyin** — already localised or
  language-neutral.
- **Re-translating from the original Chinese**, or sourcing a published Ukrainian edition — a
  separate effort if ever wanted; this spec is explicitly "translate Legge into Ukrainian".
- **A third locale**, or a general per-field translation framework — two locales, hard-coded.
- **AI interpretation text** — already generated in the requested language.
- **Markdown export** localisation of the classical text (the export currently includes no
  Judgment / line text; unchanged).
- Persisting a locale preference server-side.

## Functional requirements

- **REQ-UCT-001** — `HexagramTextCatalog::textFor($n, 'uk')` returns a `{judgment, image,
  lineStatements[6]}` whose values are Ukrainian for every `$n` in 1..64; `textFor($n)` and
  `textFor($n, 'en')` are unchanged.
- **REQ-UCT-002** — `HexagramSequenceCatalog::precedentFor($n, 'uk')` returns Ukrainian for
  every `$n` in 3..64 and `null` for 1, 2; the `en` behaviour is unchanged.
- **REQ-UCT-003** — `HexagramTextCatalog::specialTextFor($n, 'uk')` returns Ukrainian for
  `$n` ∈ {1, 2}, `null` otherwise.
- **REQ-UCT-004** — With `?lang=uk`, `GET /api/hexagrams/{id}` returns Ukrainian `judgment` /
  `image` / `lineStatements` / `sequencePrecedent`; without it (or `?lang=en`) it returns
  English. Same for the list, `from-lines`, and `compare` endpoints.
- **REQ-UCT-005** — With `?lang=uk`, `GET /api/consultations/{id}` returns Ukrainian
  `readingGuidance.refs[].text` and `specialTextContent`.
- **REQ-UCT-006** — Switching the app language on a hexagram-detail / list / compare /
  consultation page re-fetches and re-renders the classical text in the new language.

## Non-functional requirements

- **REQ-UCT-020** — Every Ukrainian entry is non-empty and structurally parallel to its
  English counterpart (same key set; `lineStatements` has 6). A `yijing-core` test asserts
  full 1..64 / 3..64 coverage and non-emptiness.
- **REQ-UCT-021** — `phpstan` level 8 + `php-cs-fixer` clean in `yijing-core` and `apps/api`;
  `npm run verify` passes.
- **REQ-UCT-022** — The `…Uk` classes carry the provenance docblock described above.

## Data requirements

- ~577 Ukrainian passages: 64 × (1 judgment + 1 image + 6 line statements) + 63 Xù Guà + 2
  special. Machine-assisted translation of the corresponding Legge English already in the
  repo. Compiled into `HexagramTextCatalogUk` / `HexagramSequenceCatalogUk`. No database.

## API requirements

- `?lang=en|uk` (default `en`, invalid → `en`) on `GET /api/hexagrams`,
  `/api/hexagrams/{id}`, `/api/hexagrams/from-lines`, `/api/hexagrams/compare`,
  `/api/consultations/{id}`. No other endpoint changes; no shape change.

## Edge cases

- `?lang=fr` or `?lang=` → treated as `en`.
- A `uk` catalog key missing at runtime → fall back to the English entry for that field
  (defensive; the tests guarantee full coverage so this should never fire in practice).
- Hexagrams 1 / 2 with `?lang=uk` → Ukrainian `judgment` / `image` / line texts **and**
  Ukrainian `specialTextContent` in a consultation reading.
- Language switch mid-session on a long-lived page → the `watch(locale)` re-fetch swaps the
  text; no full reload.

## Acceptance criteria

- [x] `HexagramTextCatalogUk` / `HexagramSequenceCatalogUk` cover 1..64 / 3..64 with non-empty
      Ukrainian, structurally parallel to the English catalogs; `…::*For(..., 'uk')` return them.
- [x] `GET /api/hexagrams/2?lang=uk` → Ukrainian `judgment` / `image` / `lineStatements` /
      `sequencePrecedent`; `?lang=en` (and no param) → the current English.
- [x] `GET /api/consultations/{id}?lang=uk` for a reading with changing lines → Ukrainian
      `readingGuidance.refs[].text`.
- [x] On the hexagram detail page, switching EN→UK re-renders the Judgment/Image/line
      texts/sequence sentence in Ukrainian without a manual reload.
- [x] `npm run verify` passes; `phpstan` + `php-cs-fixer` clean.
