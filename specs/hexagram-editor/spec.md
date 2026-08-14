# SPEC-016 — Visual Hexagram Editor

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-15

## Problem

Feature 23 of the plan's next batch asks for a UI where a user can manually toggle all six
lines and see the resulting hexagram's number, name, trigrams, and relationships update live —
purely as a structural exploration tool (distinct from `NewConsultationPage`'s existing manual
casting form, SPEC-009, which asks for a *question* and *persists a consultation*; toggling lines
here must never create a database row). No existing API endpoint computes a hexagram from an
arbitrary 6-line pattern without persisting something: `POST /api/consultations` (manual method)
requires a question and always writes to SQLite. `Hexagram::fromLines()` — the domain method that
would do this computation — is already `packages/yijing-core` public API and already exercised by
`ManualMethod`; it just isn't reachable read-only over HTTP yet.

## Purpose

Add a read-only `GET /api/hexagrams/from-lines` endpoint that computes a hexagram (with the same
full detail `GET /api/hexagrams/{id}` returns, including SPEC-014's relationships) from six
caller-supplied line polarities, with no persistence — and a new `/hexagrams/editor` page with six
toggles driving it, so flipping a line updates number/name/trigrams/relationships live, with zero
hexagram-computation logic duplicated in `apps/web`.

## Scope

- `GET /api/hexagrams/from-lines?lines=yang,yin,yang,yang,yin,yin` (bottom to top, 6
  comma-separated `yin`/`yang` values) — computes `Hexagram::fromLines()` from the parsed
  polarities and returns the exact same JSON shape `GET /api/hexagrams/{id}` already returns
  (including `relationships`), reusing `HexagramController`'s existing `toJson()`. No `changing`
  flag — this is a structural/identity tool, not a casting tool; changing-line semantics stay
  exclusive to the consultation flow (SPEC-004/005/009).
- `HexagramController` gains this action; no new controller class (mirrors `index()`/`show()`
  living together).
- `entities/hexagram` gains `computeHexagramFromLines(polarities: LinePolarity[]): Promise<Hexagram>`.
- New page `HexagramEditorPage.vue` at `/hexagrams/editor`: six yin/yang toggles (bottom to top,
  matching `NewConsultationPage`'s existing line-ordering convention), a live `HexagramLines`
  diagram, and the computed number/name/trigrams/relationships — all values sourced from the new
  endpoint's response, never computed in the component.
- A link to `/hexagrams/editor` from `HexagramListPage` so the new page is discoverable.

## Out of scope

- **Changing lines / casting a reading from the editor.** This tool answers "what hexagram is
  this pattern," not "what reading resulted from this cast" — `NewConsultationPage`'s existing
  manual-casting form (with its question field and persistence) remains the only way to create a
  consultation.
- **Persisting or sharing an edited pattern** (e.g. a shareable URL encoding the 6 lines). Not
  asked for; the editor is a live, ephemeral exploration tool.
- **Editing lines by clicking directly on the `HexagramLines` diagram.** Explicit toggle controls
  (matching `NewConsultationPage`'s existing radio-button pattern) are simpler and already
  established in this codebase; a click-the-diagram interaction is a separate, unvalidated design
  choice not asked for by the feature text ("a UI where the user can modify all six lines," not a
  specific interaction style).

## User behavior

```
GET /api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,yin
  -> 200, same JSON shape as GET /api/hexagrams/11 (this pattern IS hexagram 11, Tai), including
     relationships.

GET /api/hexagrams/from-lines?lines=yang,yang,yang
  -> 422 {"error": "..."} (wrong count)

GET /api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,maybe
  -> 422 {"error": "..."} (invalid polarity value)

/hexagrams/editor
  -> six toggles, default all yang (hexagram 1, Qian)
  -> flipping line 4 to yin immediately re-fetches and shows: "11. 泰 (Tai)", its trigrams
     (☰ Qian / ☷ Kun), and its relationships (nuclear 54, reversed/complement 12) — no page
     reload, no client-side hexagram math.
```

## Functional requirements

- **REQ-HEXEDIT-001** — `GET /api/hexagrams/from-lines` MUST accept a `lines` query parameter of
  exactly 6 comma-separated `yin`/`yang` values (bottom to top) and respond `200` with the
  computed hexagram in the same shape `GET /api/hexagrams/{id}` returns.
- **REQ-HEXEDIT-002** — A `lines` parameter with any count other than 6, or containing a value
  other than `yin`/`yang`, MUST respond `422` with a descriptive error message — never a `500` or
  an uncaught exception.
- **REQ-HEXEDIT-003** — The endpoint MUST NOT write to the database — purely computed via
  `Hexagram::fromLines()`, matching `HexagramController`'s existing PDO-free precedent (SPEC-003).
- **REQ-HEXEDIT-004** — `/hexagrams/editor` MUST re-fetch and re-render the computed hexagram
  whenever any of the six toggles changes, with no hexagram-identity, trigram, or relationship
  computation performed in `apps/web`.
- **REQ-HEXEDIT-005** — `/hexagrams/editor` MUST default to all-yang (hexagram 1) on initial load,
  fetched the same way as any subsequent toggle (no special-cased initial state).

## Non-functional requirements

- **REQ-HEXEDIT-006** — No new `YijingRelations`/`Hexagram` domain methods — this spec only wires
  the existing `Hexagram::fromLines()` (already used by `ManualMethod`) into a new read-only route.

## Data requirements

None — no persistence, matching `HexagramController`'s existing endpoints.

## API requirements

`GET /api/hexagrams/from-lines` — see "User behavior"/"Functional requirements" above. No other
endpoint's behavior changes.

## Edge cases

- All-yin input (`yin,yin,yin,yin,yin,yin`) → hexagram 2 (Kun), same as any other valid pattern —
  no special-casing.
- Query parameter entirely absent → `422` (same as wrong count — zero values parsed).

## Acceptance criteria

- [x] `GET /api/hexagrams/from-lines?lines=yang,yang,yang,yin,yin,yin` returns hexagram 11 (Tai)
      with the same shape (including `relationships`) as `GET /api/hexagrams/11`.
- [x] Wrong line count or an invalid polarity value → `422`, not `500`.
- [x] `/hexagrams/editor` defaults to hexagram 1 (Qian) and updates live (number, name, trigrams,
      relationships) as each of the six toggles is flipped.
- [x] `HexagramListPage` links to `/hexagrams/editor`.
- [x] No hexagram-computation logic (pattern → King Wen number, trigram derivation, relationship
      math) exists anywhere under `apps/web`.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI: flip several lines, confirm the preview
      matches the equivalent `/hexagrams/{number}` page.
