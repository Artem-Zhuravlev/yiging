# Tasks — Single-Consultation Markdown Export (SPEC-051)

- [x] **TASK-MD-001** — `shared/lib/consultationMarkdown.ts`: pure
      `consultationToMarkdown(consultation, t)` (header + metadata + hexagrams + changing lines
      always; notes / tags / context / outcome / follow-up sections only when non-empty;
      trailing `---` + exported-on line) and `slugifyForFilename(question)`. → REQ-MD-002, 005, 006
- [x] **TASK-MD-002** — `ConsultationPage.vue`: "Copy as Markdown" (clipboard + success toast;
      inline error on failure) and "Download .md" (client-side `Blob`, filename from the
      question) in the print-hidden action row. → REQ-MD-001, 003, 004
- [x] **TASK-MD-003** — i18n: `consultationPage.copyMarkdown` / `downloadMarkdown` /
      `markdownCopied` / `copyMarkdownError` + the `markdown.*` render labels, reusing existing
      keys where they fit (en + uk). → REQ-MD-020
- [x] **TASK-MD-004** — `consultationMarkdown.spec.ts`: minimal case omits empty sections;
      full case includes every section + trailing lines; `slugifyForFilename` cases.
      → REQ-MD-021
- [x] **TASK-MD-005** — `ConsultationPage.spec.ts`: both controls present; copy calls
      `clipboard.writeText` with the question in it; clipboard rejection → inline error, no
      toast; download triggers an `<a download>`/`createObjectURL`. → REQ-MD-021
- [x] **TASK-MD-006** — `npm run verify` green; browser pass (copy → paste shows the render +
      toast; download → named `.md`; sections present/omitted correctly); fill `plan.md` note;
      flip `spec.md` → `implemented`; add SPEC-051 to both README tables. → REQ-MD-021