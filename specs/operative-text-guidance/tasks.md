# Tasks — Operative Text Guidance (SPEC-052)

## yijing-core

- [x] **TASK-OTG-001** — `Yijing\Core\ReadingRule` enum (kebab-case, 7 cases, `fromCount(int)`).
      → REQ-OTG-001
- [x] **TASK-OTG-002** — `Yijing\Core\CastReadingRef` (`hexagram`, `kind`, `?position`,
      `governing`, `toArray()`). → REQ-OTG-001, 003
- [x] **TASK-OTG-003** — `Yijing\Core\CastReading` + `::forCast(Hexagram $primary,
      list<int> $changingPositions)` implementing the 7-row table, folding `changeLine()` for
      the resulting, `use-nine`/`use-six` keyed on the primary King Wen number; `toArray()`;
      `InvalidArgumentException` on dup/out-of-range positions. → REQ-OTG-001, 002, 003
- [x] **TASK-OTG-004** — `HexagramTextCatalog`: `private const SPECIAL = [1 => …, 2 => …]`
      (Legge, verified vs baharna.com + ctext.org) + `specialTextFor(int): ?string`.
      → REQ-OTG-004
- [x] **TASK-OTG-005** — `tests/CastReadingTest.php` (all 7 counts; Qián/Kūn/other n=6; n=4/5
      unchanged-position selection; throw on bad positions) + `HexagramTextCatalogTest`
      `specialTextFor` cases. `composer test`/`stan`/`lint` green. → REQ-OTG-020, 021

## API

- [x] **TASK-OTG-006** — `ConsultationController::toJsonWithRepeats()` adds `readingGuidance`
      via `CastReading::forCast(...)` + a `readingGuidanceToJson()` that resolves each ref's
      `text` from the primary/resulting `Hexagram` and adds `specialTextContent` when set.
      → REQ-OTG-005
- [x] **TASK-OTG-007** — `ConsultationControllerTest`: no-changing / one-changing / all-six
      (`use-nine`) cases assert `rule` + resolved `text` + `specialTextContent`; assert
      `POST /api/consultations` and `GET /api/consultations` responses have no
      `readingGuidance`. `composer test`/`stan`/`lint` green. → REQ-OTG-005, 021

## Frontend

- [x] **TASK-OTG-008** — `entities/consultation/model.ts`: `ReadingGuidance` /
      `ReadingGuidanceRef`; `ConsultationDetail.readingGuidance`. → REQ-OTG-006
- [x] **TASK-OTG-009** — `ConsultationPage.vue`: `readingGuidance` ref set on load; a
      "How to read this cast" panel (rule sentence + per-ref label/text with the governing one
      emphasised + special-text block) after the changing-lines line. → REQ-OTG-006
- [x] **TASK-OTG-010** — i18n `readingGuidance.*` (title, primary/resulting/judgment/governing/
      useNine/useSix, `rule.<7 cases>` sentences) (en + uk). → REQ-OTG-022
- [x] **TASK-OTG-011** — `ConsultationPage.spec.ts`: with a `readingGuidance` in the mocked
      detail, the panel renders the rule sentence and the ref text(s); a `use-nine` fixture
      shows the special block. → REQ-OTG-022

## Close-out

- [x] **TASK-OTG-012** — `packages/yijing-core` + `apps/api` `composer test`/`stan`/`lint`
      green; `npm run verify` green; browser pass (0/1/2/6-changing casts + a Qián all-changing
      "Use Nine"); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-052 to both
      README tables. → REQ-OTG-021, 022