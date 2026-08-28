# SPEC-051 — Single-Consultation Markdown Export

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

A user who keeps a practice notebook (Obsidian, Logseq, a plain journal repo) has no clean way
to pull one consultation out of the app as text. The options today are "Print / Export" (browser
PDF, SPEC-027) and the whole-history JSON backup (SPEC-028) — neither gives you one reading as
readable prose you can paste into your notes.

## Purpose

Add "Copy as Markdown" and "Download .md" for a single consultation on its detail page: a plain
client-side render of the consultation's own data (question, method, date, hexagrams, changing
lines, notes, tags, context, outcome, follow-up links) as Markdown. No AI interpretation, no
API, no server involvement.

## Scope

- New `shared/lib/consultationMarkdown.ts` — a pure `consultationToMarkdown(consultation:
  Consultation): string` that renders:
  - `# {question}`
  - a metadata line: method · local date
  - `**Primary:** {n}. {chineseName} ({pinyin})` / `**Resulting:** …`
  - `**Changing lines:** 1, 4` (or "none")
  - `## Notes` — a bullet per note, `**{label}:** {text}` (only if any)
  - `**Tags:** a, b` (only if any)
  - `## Context` — a `**Label:** value` line per non-empty context field (only if any)
  - `## Outcome` — `**What happened:** …` / `**Outcome:** …` / `**Reflection:** …` for the
    non-empty outcome fields, plus a "recorded {date}" line (only if an outcome exists)
  - `## Follow-up` — "Follows up on: {question}" and/or a bullet list of follow-up questions
    (only if present)
  - A trailing `---` and a small "Exported from Yijing on {date}" line.
  - Section headings and field labels are localised (en + uk); the consultation's own text is
    verbatim.
- `ConsultationPage.vue`: in the existing `print-hidden` action row (next to "Print / Export"),
  add "Copy as Markdown" (`navigator.clipboard.writeText`, then a success toast) and
  "Download .md" (a client-side `Blob` download named
  `yijing-{first-40-chars-of-question-slugified}.md`, same technique as the backup export).
- Reuse `useToastSuccess` for the copy confirmation; a clipboard failure sets a small inline
  message like the existing "Copy Share Link" error does.

## Out of scope

- **Including the AI interpretation.** It's never persisted (SPEC-008) and isn't loaded unless
  the user requested it; keeping it out matches the SPEC-029 share page's field set. A later
  spec could add "include the currently-loaded interpretation" as an option.
- **Exporting multiple consultations to Markdown**, a combined document, or front-matter/YAML
  metadata for a specific note-taking tool.
- **A server-side render or a new endpoint.** Pure client-side from the already-fetched
  `Consultation`.
- **Round-tripping** (importing Markdown back). One-way export only; JSON backup (SPEC-028)
  remains the round-trippable format.
- **Changing "Print / Export"** (SPEC-027) — it stays.

## Functional requirements

- **REQ-MD-001** — The consultation detail page has "Copy as Markdown" and "Download .md"
  controls in its (print-hidden) action row.
- **REQ-MD-002** — Both render the same Markdown string from `consultationToMarkdown`, covering
  question, method, date, primary/resulting hexagram, changing lines, and — only when present —
  notes, tags, context fields, outcome, and follow-up links.
- **REQ-MD-003** — "Copy as Markdown" writes to the clipboard and shows a success toast; a
  clipboard error shows an inline message and no toast.
- **REQ-MD-004** — "Download .md" downloads a `.md` file named from the question; no network
  request.
- **REQ-MD-005** — The rendered Markdown contains no AI interpretation content.
- **REQ-MD-006** — Section headings / field labels are localised; the consultation's own text
  is unchanged.

## Non-functional requirements

- **REQ-MD-020** — New strings localised (en + uk).
- **REQ-MD-021** — `npm run verify` passes; `consultationMarkdown` has unit tests for the
  minimal case and the fully-populated case, and `ConsultationPage.spec.ts` covers the two
  controls.

## Data requirements

None. Consumes the already-loaded `Consultation`.

## API requirements

None.

## Edge cases

- A consultation with no notes / tags / context / outcome / follow-ups → only the header,
  metadata, hexagrams, and changing-lines sections render; no empty headings.
- A question with characters unsafe for a filename (`/`, `:`, newlines) → slugified to
  `[a-z0-9-]`, truncated to ~40 chars, falling back to `consultation` if empty.
- `navigator.clipboard` unavailable / permission denied → inline error, no toast, no throw.
- Very long note/context text → included verbatim; Markdown has no length limit here.

## Acceptance criteria

- [x] "Copy as Markdown" copies the rendering + toasts; "Download .md" downloads it — live:
      copy → `# question` + localised sections, toast "Скопійовано як Markdown"; download →
      `yijing-should-i-take-the-new-job-offer.md`. `ConsultationPage.spec.ts`.
- [x] Markdown includes hexagrams + changing lines + present sections, excludes AI
      interpretation — `consultationMarkdown.spec.ts` (minimal + fully-populated).
- [x] Empty sections omitted — live (bare `## Notes`/`## Context`/`## Follow-up` absent) + spec.
- [x] `npm run verify` passes (web 205, api 323, yijing-core 55).

## Implementation note (2026-08-28)

- `shared/lib/consultationMarkdown.ts`: pure `consultationToMarkdown(consultation, t)` (takes
  the i18n `t` so it stays framework-free and unit-testable) + `slugifyForFilename`. Sections
  after the always-present header/metadata/hexagrams/changing-lines are pushed only when their
  data is non-empty; trailing `---` + "Exported from Yijing on {date}".
- `ConsultationPage.vue`: `copyMarkdown()` (`navigator.clipboard.writeText` → `notifySaved`;
  catch → inline `markdownError`) and `downloadMarkdown()` (client-side `Blob` +
  `<a download>`), two `Button`s in the existing print-hidden action row.
- i18n: `consultationPage.{copyMarkdown,downloadMarkdown,markdownCopied,copyMarkdownError}` +
  a `markdown.*` block of render labels (en + uk).
