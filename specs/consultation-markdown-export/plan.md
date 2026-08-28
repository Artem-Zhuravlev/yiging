# Plan — Single-Consultation Markdown Export (SPEC-051)

## Files

### New
- `apps/web/src/shared/lib/consultationMarkdown.ts`
  - `consultationToMarkdown(c: Consultation, t: (key: string, named?: Record<string, unknown>) => string): string`
    — takes the i18n `t` so labels are localised without the module importing vue-i18n itself
    (keeps it a pure function, easy to test with a stub `t`).
  - builds an array of lines, `.filter(Boolean).join('\n')`; sections pushed only when their
    source data is non-empty.
  - `slugifyForFilename(question: string): string` exported too — lower-case, non-alnum → `-`,
    collapse repeats, trim `-`, slice(0, 40), `|| 'consultation'`.
- `apps/web/src/shared/lib/consultationMarkdown.spec.ts`

### Changed
- `apps/web/src/pages/consultations/ConsultationPage.vue`
  - import `consultationToMarkdown`, `slugifyForFilename`.
  - `const markdownError = ref('')`.
  - `function buildMarkdown(): string` → `consultationToMarkdown(state.consultation, t)` (guard
    `state.status === 'loaded'`).
  - `async function copyMarkdown()` → `navigator.clipboard.writeText(buildMarkdown())` →
    `notifySaved('consultationPage.markdownCopied')`; catch → `markdownError.value = t('consultationPage.copyMarkdownError')`.
  - `function downloadMarkdown()` → `Blob([md], { type: 'text/markdown' })` → temp `<a download>`
    click → revoke (same pattern as `exportConsultationsBackup`).
  - Template: in the `print-hidden flex gap-3 flex-wrap` row that holds the favourite toggle /
    "Print / Export" / "Copy Share Link", add two `Button text size="small"`:
    `t('consultationPage.copyMarkdown')` and `t('consultationPage.downloadMarkdown')`.
    Add `<Message v-if="markdownError" severity="error" role="alert" class="mt-1">` near the
    existing copy-link error message.
- `apps/web/src/i18n/locales/{en,uk}.ts` — under `consultationPage`:
  `copyMarkdown` / `downloadMarkdown` / `markdownCopied` / `copyMarkdownError`; and a
  `markdown` block for the render labels: `metadata` (or reuse), `primary`, `resulting`,
  `changingLines`, `noChangingLines`, `notes`, `tags`, `context`, `outcome`, `whatHappened`,
  `outcomeField`, `reflection`, `recorded`, `followUp`, `followsUpOn`, `exportedOn`.
  Where a good key already exists (`consultation.notes`, `consultation.outcome`,
  `contextFields.*`, `newConsultation.followUpTo`) reuse it and only add what's missing —
  decide during implementation, keep new keys minimal.

## Markdown shape (example, fully populated)

```
# Should I take the offer?

three_coins · 14/08/2026

**Primary:** 1. 乾 (Qián)
**Resulting:** 44. 姤 (Gòu)
**Changing lines:** 1

## Notes

- **Before:** Feeling torn.
- **After:** Took it.

**Tags:** career, money

## Context

- **Context:** New role, more travel.
- **What you want to understand:** Whether the trade-off is worth it.

## Outcome

- **What actually happened:** Accepted; relocated.
- **Outcome:** Positive so far.
- **Reflection:** The reading's caution about "hidden" was apt.

_Recorded 21/08/2026_

## Follow-up

Follows up on: Earlier job question?

- What should I watch for in the first month?

---

_Exported from Yijing on 28/08/2026_
```

## Testing

- `consultationMarkdown.spec.ts` (stub `t` = `(k) => k` so assertions match keys, plus a couple
  with interpolation):
  - minimal consultation (no notes/tags/context/outcome/follow-ups) → contains `# {question}`,
    the primary/resulting lines, the changing-lines line; does NOT contain `## Notes` /
    `## Outcome` / `## Context` / `## Follow-up`.
  - fully-populated → each section present; a note bullet; tags line; an outcome field; a
    follow-up bullet; the trailing `---` + exported-on line.
  - `slugifyForFilename('Should I take the offer?!')` → `should-i-take-the-offer`;
    `slugifyForFilename('   ')` → `consultation`.
- `ConsultationPage.spec.ts`:
  - "Copy as Markdown" present; clicking it calls `navigator.clipboard.writeText` with a string
    containing the question (stub `navigator.clipboard`).
  - clipboard rejects → an inline error appears, `notifySaved` not called (spy or check no
    toast text — simplest: stub clipboard to reject and assert the error message text).
  - "Download .md" present; clicking it creates and clicks an `<a download$>` (spy on
    `HTMLAnchorElement.prototype.click` or `URL.createObjectURL`).

## Verify

`npm run verify`; browser on `/consultations/:id`: "Copy as Markdown" → paste elsewhere shows
the rendering + toast; "Download .md" downloads a sensibly-named file; a consultation with an
outcome and notes includes those sections; a bare one doesn't show empty headings.

## Verification note (2026-08-28)

- `npm run verify` green (web 205 tests incl. `consultationMarkdown.spec.ts` + 3 new
  `ConsultationPage` tests; api 323; yijing-core 55).
- Live pass on `/consultations/:id`: "Copy as Markdown" copied a clean localised render
  (`# question`, method · date, `**Первинна/Результуюча/Змінні лінії**`, only the non-empty
  `## Результат` section, trailing `---` + "Експортовано з Yijing …", no AI content) and
  toasted "Скопійовано як Markdown". "Download .md" produced
  `yijing-should-i-take-the-new-job-offer.md`.
