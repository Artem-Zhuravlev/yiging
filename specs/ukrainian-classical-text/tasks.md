# Tasks — Ukrainian Classical Text (SPEC-057)

## yijing-core

- [x] **TASK-UCT-001** — `HexagramTextCatalogUk` — `ENTRIES` (1..64: judgment, image,
      6 lineStatements) + `SPECIAL` (1, 2), Ukrainian; provenance docblock. → REQ-UCT-001/003/022
- [x] **TASK-UCT-002** — `HexagramSequenceCatalogUk` — `ENTRIES` (3..64), Ukrainian; docblock.
      → REQ-UCT-002/022
- [x] **TASK-UCT-003** — `HexagramTextCatalog::textFor/specialTextFor` +
      `HexagramSequenceCatalog::precedentFor` gain `string $locale = 'en'` with uk lookup +
      en fallback. → REQ-UCT-001..003
- [x] **TASK-UCT-004** — `HexagramTextCatalogUkTest`, `HexagramSequenceCatalogUkTest`
      (full coverage, non-empty, Cyrillic, structural parity; en path unchanged).
      `composer test`/`stan`/`lint` green. → REQ-UCT-020, 021

## API

- [x] **TASK-UCT-005** — `App\Core\RequestLocale::from(Request): 'en'|'uk'`. → REQ-UCT-004
- [x] **TASK-UCT-006** — `HexagramController`: `index()`/`show()`/`fromLines()`/`compare()`
      read `?lang`, `toJson()` overlays localized judgment/image/lineStatements/
      sequencePrecedent. → REQ-UCT-004
- [x] **TASK-UCT-007** — `ConsultationController::show()` threads locale into
      `readingGuidanceToJson()` (ref text + specialTextContent from the catalog).
      → REQ-UCT-005
- [x] **TASK-UCT-008** — `HexagramControllerTest` + `ConsultationControllerTest`: `?lang=uk`
      → Cyrillic; `?lang=en` / none / `?lang=fr` → English. → REQ-UCT-021

## Frontend

- [x] **TASK-UCT-009** — `fetchHexagram` / `fetchHexagrams` / `compareHexagrams` /
      `fetchConsultation` gain optional `lang`; append `?lang=` when not `en`. → REQ-UCT-006
- [x] **TASK-UCT-010** — `HexagramDetailPage` / `HexagramListPage` / `HexagramComparePage` /
      `ConsultationPage` / `SharedConsultationPage`: pass `locale.value`, `watch(locale)` to
      re-fetch. → REQ-UCT-006
- [x] **TASK-UCT-011** — i18n `hexagramDetail.sourceSuffix` (uk) notes the text is translated
      from Legge's English. → REQ-UCT-021
- [x] **TASK-UCT-012** — api specs (`?lang=uk` hit) + a `HexagramDetailPage.spec` locale-switch
      re-fetch assertion. → REQ-UCT-021

## Close-out

- [x] **TASK-UCT-013** — `yijing-core` + `apps/api` `composer test`/`stan`/`lint` green;
      `npm run verify` green; browser + curl pass (EN⇄UK toggle on hexagram detail +
      consultation); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-057 to both
      README tables. → REQ-UCT-021
