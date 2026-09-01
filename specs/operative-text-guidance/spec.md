# SPEC-052 — Operative Text Guidance (Changing-Line Reading Rules)

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

The consultation detail page shows the primary hexagram, the resulting hexagram, and a list of
changing-line positions — and then leaves the user to figure out *which text is actually the
answer*. In practice the operative text depends on how many lines changed. The standard
Song-dynasty synthesis (Zhu Xi, *Zhouyi benyi*) — reproduced in the Wilhelm/Baynes
introduction, Alfred Huang, and most modern manuals — gives a precise rule per case:

| changing lines | what you read |
| -------------- | ------------- |
| 0 | the **Judgment of the primary** hexagram |
| 1 | the **text of that changing line** (primary) |
| 2 | the **texts of both changing lines** (primary); the **upper** governs |
| 3 | the **Judgments of both** hexagrams; the **primary** governs |
| 4 | the **two *unchanged* lines of the resulting** hexagram; the **lower** governs |
| 5 | the **one *unchanged* line of the resulting** hexagram |
| 6 | primary is **Qián** → "Use Nine"; primary is **Kūn** → "Use Six"; otherwise the **Judgment of the resulting** hexagram |

Right now none of this is surfaced. This is rules-based, needs no AI, and is exactly the kind
of thing a serious study tool should do.

## Purpose

Compute, for every cast, a structured "how to read this" guidance object in `yijing-core`,
expose it on the consultation detail response, and render it on the consultation page as a
short panel that names the rule and shows the operative text(s), marking the governing one.

## Scope

### `packages/yijing-core`

- `Yijing\Core\ReadingRule` enum: `NoChangingLines`, `OneChangingLine`, `TwoChangingLines`,
  `ThreeChangingLines`, `FourChangingLines`, `FiveChangingLines`, `SixChangingLines`.
- `Yijing\Core\CastReadingRef` — readonly: `hexagram: 'primary'|'resulting'`,
  `kind: 'judgment'|'line'`, `?int $position` (1–6, set only when `kind === 'line'`),
  `bool $governing`.
- `Yijing\Core\CastReading` — readonly value object:
  - `int $changingLineCount`
  - `ReadingRule $rule`
  - `list<CastReadingRef> $refs` — the judgment(s) and/or line(s) to read, in reading order;
    exactly one has `governing === true` when there is more than one, and the single ref is
    `governing === true` when there is exactly one; empty for a pure special-text case.
  - `?string $specialText` — `'use-nine'` | `'use-six'` | `null`.
  - `public static function forCast(Hexagram $primary, list<int> $changingPositions): self` —
    the only constructor. Sorts/validates positions (1–6, unique), derives the resulting
    hexagram by folding `changeLine()`, applies the table above.
- `HexagramTextCatalog::specialTextFor(int $kingWenNumber): ?string` — returns the "Use Nine"
  text for hexagram 1, the "Use Six" text for hexagram 2, `null` for the other 62. Two new
  Legge strings added to the catalog's data (same provenance rule as SPEC-002: transcribed
  from the baharna.com Legge digitization and cross-checked against ctext.org). Working
  transcription (to verify during implementation):
  - **1 / Use Nine:** "(The lines of this hexagram are all strong and undivided, as appears
    from) the use of the number NINE. If the host of dragons (thus) appearing were to divest
    themselves of their heads, there would be good fortune."
  - **2 / Use Six:** "(The lines of this hexagram are all weak and divided, as appears from)
    the use of the number SIX. If those (represented here) will maintain a firm and correct
    persistence, there will be advantage."

### API

- `GET /api/consultations/{id}` (the `toJsonWithRepeats` path only, like `repeats`) gains a
  `readingGuidance` block:
  ```json
  {
    "changingLineCount": 2,
    "rule": "two-changing-lines",
    "refs": [
      { "hexagram": "primary", "kind": "line", "position": 3, "governing": false, "text": "…" },
      { "hexagram": "primary", "kind": "line", "position": 5, "governing": true,  "text": "…" }
    ],
    "specialText": null
  }
  ```
  - `rule` is the enum in kebab-case.
  - Each ref carries the resolved `text`: a `line` ref → the corresponding
    `lineStatements[position-1]` of the named hexagram; a `judgment` ref → that hexagram's
    `judgment`. When `specialText` is set, `refs` is empty and the block also carries
    `"specialTextContent": "…"` (from `specialTextFor`).
- No new endpoint; create/update/list responses are unchanged (detail-only, per SPEC-023's
  precedent for `repeats`).

### Frontend

- `entities/consultation/model.ts`: `ReadingGuidance` / `ReadingGuidanceRef` types;
  `ConsultationDetail` gains `readingGuidance: ReadingGuidance`.
- `ConsultationPage.vue`: a panel titled "How to read this cast" (localised), placed after the
  changing-lines line and before Notes. It shows:
  - one static sentence for the `rule` (e.g. "Two changing lines — read both; the upper line
    governs.");
  - then each `ref` as a labelled block: "Primary · Line 5 · governing" + the text (the
    governing one visually emphasised); a `judgment` ref reads "Primary · Judgment" + text;
  - a special-text case shows "Qián · Use Nine" + `specialTextContent`.
- Localised (en + uk): the panel title and the ~9 rule sentences (`primary`/`resulting`,
  `judgment`, `line {n}`, `governing` labels reuse existing keys where they exist —
  `consultation.primaryHeading` etc. are headings, so add compact `readingGuidance.*` labels).

## Out of scope

- **Feeding the operative text into the AI interpretation prompt.** A natural and valuable
  follow-up, but it touches the byte-identical-prompt contract of SPEC-008/011/033 — separate
  spec.
- **Intra-hexagram line dynamics** (correspondence 應, correctness of position 當位, centrality
  中, ruling line 卦主, riding/receiving 乘/承). That's the next domain feature, its own spec.
- **The yarrow-stalk probability distribution**, alternate reading schools (Jing Fang's eight
  palaces, plum-blossom), or configurable rule sets. This spec implements the one standard
  synthesis.
- **Changing which line texts / judgments the app stores** beyond the two new "Use Nine / Use
  Six" strings.
- **Showing the guidance on the print/share/list views** — detail page only (matches `repeats`).

## Functional requirements

- **REQ-OTG-001** — `CastReading::forCast()` returns, for each of the 7 changing-line counts,
  the refs and/or special text matching the table above, with exactly one governing ref when
  more than one ref is present.
- **REQ-OTG-002** — `n = 6` on hexagram 1 → `specialText = 'use-nine'`, on hexagram 2 →
  `'use-six'`, otherwise → a single `judgment` ref on `resulting`.
- **REQ-OTG-003** — `n = 2` refs are the two changing positions ascending, upper `governing`;
  `n = 4` refs are the two non-changing positions of the resulting ascending, lower
  `governing`; `n = 5` ref is the single non-changing position of the resulting.
- **REQ-OTG-004** — `HexagramTextCatalog::specialTextFor()` returns non-null only for 1 and 2.
- **REQ-OTG-005** — `GET /api/consultations/{id}` includes `readingGuidance` with each ref's
  `text` resolved from the correct hexagram, and `specialTextContent` when applicable; other
  consultation responses are unchanged.
- **REQ-OTG-006** — The consultation page shows a panel naming the rule and the operative
  text(s), with the governing one marked, for every consultation.

## Non-functional requirements

- **REQ-OTG-020** — `CastReading` is pure (no I/O), fully unit-tested in `yijing-core` against
  all 7 cases plus the Qián/Kūn n=6 branches.
- **REQ-OTG-021** — `phpstan` level 8 + `php-cs-fixer` clean across all three packages.
- **REQ-OTG-022** — New UI strings localised (en + uk); `npm run verify` passes.

## Data requirements

Two new classical-text strings in `HexagramTextCatalog` (hexagrams 1 and 2 only). No DB
schema change — `readingGuidance` is derived at response time from the stored primary King Wen
number + changing positions.

## API requirements

- `GET /api/consultations/{id}` → response gains `readingGuidance`
  (`{ changingLineCount, rule, refs: [{hexagram, kind, position?, governing, text}], specialText,
  specialTextContent? }`).

## Edge cases

- `n = 0` → `rule: no-changing-lines`, one `judgment` ref on `primary` (`governing: true`),
  `specialText: null`.
- `n = 3` → two `judgment` refs (`primary` governing, then `resulting`), no line refs.
- `n = 6`, primary is Qián, resulting is Kūn → still `use-nine` (keyed on the *primary*).
- Duplicate or out-of-range positions passed to `forCast()` → `InvalidArgumentException`
  (defensive; the API only ever passes `Consultation::changingLinePositions()`, which is
  already clean).
- A hexagram whose `lineStatements` entry for a referenced position is empty string → the API
  still returns the (empty) `text`; the frontend shows the label with no body. (Shouldn't
  happen — the catalog is complete.)

## Acceptance criteria

- [x] `CastReading::forCast()` produces the correct refs / special text for all 7 counts and
      the Qián/Kūn/other n=6 branches — `CastReadingTest` (11 tests).
- [x] `specialTextFor()` returns the Use Nine / Use Six text only for 1 / 2 — `HexagramTextCatalogTest`.
- [x] `GET /api/consultations/{id}` returns `readingGuidance` with each ref's `text` resolved;
      `POST /api/consultations` and the list response carry no `readingGuidance` —
      `ConsultationControllerTest` (4 tests) + live curl (n=0/2/6).
- [x] The consultation page shows the "How to read this cast" panel with the rule sentence and
      the operative text(s), governing ref carrying a `--p-primary-color` left accent — live on
      an n=2 cast (primary lines 2+5, line 5 accented) and an n=6 Qián cast ("Використання
      дев’яток" + Use Nine text). `ConsultationPage.spec.ts` (2 tests).
- [x] `npm run verify` passes (yijing-core 68, api 327, web 210).

## Implementation note (2026-08-28)

- `yijing-core`: `ReadingRule` (string enum), `CastReadingRef` (`judgment()`/`line()`
  factories + `toArray()`), `CastReading::forCast(Hexagram $primary, list<int> $positions)`
  implementing the 7-row table (`match` on count; unchanged positions via
  `array_diff([1..6], …)`; `use-nine`/`use-six` on `$primary->kingWenNumber`). Two Legge
  strings added as `HexagramTextCatalog::SPECIAL` + `specialTextFor()`.
- API: `toJsonWithRepeats()` adds `readingGuidance` = `CastReading::forCast(...)->toArray()`
  with each ref's `text` resolved from the primary/resulting `Hexagram` and
  `specialTextContent` when a special text applies. Detail-only, like `repeats`.
- Frontend: `ReadingGuidance`/`ReadingGuidanceRef` on `ConsultationDetail`; a `<section>` panel
  on `ConsultationPage` after the changing-lines line — rule sentence (a distinct one for the
  Qián/Kūn special case) + per-ref `Primary · Line N [· governing]` blocks with a left accent
  on the governing one + a special-text block. i18n `readingGuidance.*` (en + uk).
