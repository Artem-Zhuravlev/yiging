# SPEC-029 — Consultation Public Share Link

**Status:** verified
**Owner:** unassigned
**Last updated:** 2026-08-21

## Problem

Feature 21 of the plan's next batch asks for a public, read-only link to a single consultation
that doesn't expose the rest of the user's history. Today the only way to show someone a
consultation is to send `/consultations/{id}` directly — which renders the full editable page
(favorite toggle, note/tag forms, Save Context/Outcome, Get Interpretation) inside the normal app
shell, whose nav bar links straight to "History" (every other consultation) and "Statistics"
(aggregate personal data). Nothing today draws a line between "this one reading" and "everything
else."

This app has no authentication of any kind (see [SPEC-001](../project-architecture/spec.md) — no
accounts, no sessions). `GET /api/consultations/{id}` is already reachable, unauthenticated, by
anyone who has the id — a UUIDv4, already unguessable. **This spec adds no new access control and
cannot**: what it adds is presentational containment — a page that shows one consultation's own
content and nothing else, with no navigational path back into the rest of the app's personal
data. That distinction is stated here explicitly rather than left implied.

## Purpose

A `/share/consultations/{id}` route rendering a stripped-down, read-only view of one
consultation — no edit affordances, no app nav, and critically, no data drawn from *other*
consultations (follow-up links, repeated-pattern matches) — so sharing one link can never surface
another consultation's question text.

## Scope

- New route `/share/consultations/:id` (`consultation-share`), `meta: { public: true }`.
- New page `SharedConsultationPage.vue`, fetching via the existing `fetchConsultation(id)`
  (`GET /api/consultations/{id}`, unchanged). Renders, read-only:
  - Question, method, created-at date.
  - Both hexagram diagrams (primary/resulting) and changing-line positions.
  - Existing notes (label + text), existing tags — as plain lists, no add-forms.
  - The five context fields and the outcome fields — each rendered as plain text, only when
    non-null (context fields individually; outcome section only if the outcome itself is
    non-null), never as an editable-looking `<textarea>`.
  - A "not found" state for a 404, matching `ConsultationPage`'s existing pattern.
- Explicitly NOT rendered on the share page: the favorite state, any add-note/add-tag form, any
  Save Context/Save Outcome control, the AI Interpretation section (interpretations are never
  persisted — SPEC-008's `POST /api/interpretations/{id}` is a fresh call every time — so a
  freshly-fetched consultation has nothing to show here regardless), and — the actual privacy-
  bearing decision — `followUpTo`, `followUps`, and `repeats` are not fetched or rendered at all,
  since every one of those fields carries another consultation's `{id, question}` (see "Out of
  scope" for why this can't be filtered instead of omitted).
- `App.vue`: the main `<nav>` (Hexagrams/New Consultation/History/Statistics links) is not
  rendered when the active route's `meta.public` is `true` — replaced with just the "Yijing" site
  name, not a link to anywhere.
- `ConsultationPage.vue` gains a "Copy Share Link" button (writes
  `${location.origin}/share/consultations/{id}` via `navigator.clipboard.writeText()`) and a
  "View Public Share Page" link (`target="_blank"` to the same URL) — both `print:hidden`, per
  [SPEC-027](../consultation-print-export/spec.md)'s existing pattern for controls that shouldn't
  appear in a printed export.

## Out of scope

- **A distinct, separately-generated share token.** Explained in "Problem": with no
  authentication anywhere in this app, a second unguessable identifier alongside the already-
  unguessable UUIDv4 `id` adds no real secrecy — `/consultations/{id}` is exactly as reachable by
  anyone with the id today as `/share/consultations/{id}` would be. A real access-control layer
  (requiring a token that can be issued/revoked independently of the id) needs actual
  authentication to mean anything, which this app doesn't have.
- **Revoking or expiring a share link.** Same reasoning — there's no session/account concept to
  revoke against; the link is exactly as durable as the app's own detail link.
- **Per-field opt-out of sharing** (e.g. "share the question but not the outcome"). The share page
  shows the consultation's own full content, minus cross-references to other consultations — an
  all-or-nothing choice for this spec, matching this app's total absence of any per-field
  visibility system.
- **Filtering `followUps`/`repeats` down to "safe" entries instead of omitting them entirely.**
  There's no reliable way to know from inside one consultation's response whether a linked
  consultation is itself "safe" to reveal a question fragment of — omitting the fields entirely is
  the only choice that can't leak by construction.
- **Hiding the "Hexagrams" reference-data pages from a public-share visitor.** Those pages
  (`/hexagrams/*`) show no personal data at all — but they're reached through the main nav, which
  this spec hides entirely on public routes anyway, so there's no separate decision to make here.

## User behavior

```
/consultations/{id}
  -> "Copy Share Link" -> clipboard now has
     "http://localhost:5173/share/consultations/{id}"
  -> "View Public Share Page" (opens in new tab)

/share/consultations/{id}  (no login, no other context)
  -> nav shows just "Yijing" (no links)
  -> question, hexagrams, changing lines, notes, tags, context, outcome — all read-only
  -> no favorite indicator, no forms, no "Get Interpretation", no follow-up/repeats sections,
     no way to navigate to any other consultation from this page

/share/consultations/does-not-exist
  -> "Consultation not found." (same wording as the private detail page's 404 state)
```

## Functional requirements

- **REQ-SHARE-001** — `/share/consultations/:id` MUST render a read-only view of the consultation
  fetched from the existing `GET /api/consultations/{id}`.
- **REQ-SHARE-002** — The share page MUST NOT render any form, button, or control that mutates
  the consultation (favorite toggle, add-note, add-tag, save-context, save-outcome,
  get-interpretation).
- **REQ-SHARE-003** — The share page MUST NOT render `followUpTo`, `followUps`, or `repeats`
  data, even though the underlying API response includes them.
- **REQ-SHARE-004** — The share page MUST NOT render a link to `/consultations` (history),
  `/consultations/new`, or `/statistics`.
- **REQ-SHARE-005** — `App.vue`'s main nav MUST be replaced with just the site name (no links)
  on any route whose `meta.public` is `true`.
- **REQ-SHARE-006** — `ConsultationPage` MUST render a "Copy Share Link" button and a "View
  Public Share Page" link, both targeting `/share/consultations/{id}`.
- **REQ-SHARE-007** — A share-page request for a nonexistent id MUST show a "Consultation not
  found." message, matching `ConsultationPage`'s existing 404 handling.

## Non-functional requirements

- **REQ-SHARE-008** — No new API endpoint or response-shape change; the share page reuses
  `GET /api/consultations/{id}` exactly as-is.
- **REQ-SHARE-009** — This feature adds no access control. The spec's own text (and this
  requirement) exists so the limitation is never silently assumed away: anyone who already has a
  consultation's id can reach its data today, with or without this feature.

## Data requirements

None.

## API requirements

None — no endpoint changes.

## Edge cases

- A consultation with all five context fields `null` and no outcome → the share page renders
  question/hexagrams/notes/tags only, no empty "Context"/"Outcome" headings for fields that
  aren't there.
- `navigator.clipboard` unavailable (e.g. non-secure context) → "Copy Share Link" catches the
  rejected promise and shows an inline error rather than failing silently; "View Public Share
  Page" still works regardless, since it's a plain link.
- Visiting `/share/consultations/{id}` for a consultation that *has* follow-up links or repeated
  patterns → those are simply absent from the response consumption, not blanked-out placeholders;
  the page looks identical to one with no follow-ups/repeats at all.

## Acceptance criteria

- [x] `/share/consultations/{id}` renders question, hexagrams, changing lines, notes, tags,
      context, outcome, read-only.
- [x] No mutating control (favorite, forms, save buttons, get-interpretation) renders on the
      share page.
- [x] No follow-up/repeats data renders on the share page, even for a consultation that has them.
- [x] The nav on the share page shows only the site name, no links.
- [x] `ConsultationPage` has working "Copy Share Link" and "View Public Share Page" controls.
- [x] A nonexistent id shows "Consultation not found." on the share page.
- [x] `npm run verify` passes end to end.
- [x] Manually verified against the real running API/UI.
