# Plan — Rich Consultation Context (SPEC-019)

**Depends on spec status:** `approved`

## Technical approach

- **Migration** `database/migrations/2026_08_15_000001_add_consultation_context_fields.php`:
  ```sql
  ALTER TABLE consultations ADD COLUMN context TEXT;
  ALTER TABLE consultations ADD COLUMN what_happened_before TEXT;
  ALTER TABLE consultations ADD COLUMN what_user_wants_to_understand TEXT;
  ALTER TABLE consultations ADD COLUMN background_information TEXT;
  ALTER TABLE consultations ADD COLUMN initial_interpretation TEXT;
  ```
  All nullable, no default needed (SQLite defaults new columns to `NULL`). Additive only.
- **`Consultation`** (`apps/api/src/Readings/Consultation.php`):
  - Constructor gains five new nullable `?string` properties after `tags`.
  - `create()` gains five new optional `?string $context = null, ...` params, each validated via
    a new private static `validateContextField(?string $value, string $fieldName): void` helper
    (avoids repeating the same length-check five times) — mirrors the existing
    `MAX_QUESTION_LENGTH` check's shape but parameterized over field name for the error message.
  - `reconstitute()` gains the five fields as required params (repository always has a value —
    `null` or a string — to pass, since the columns always exist post-migration).
  - New `withUpdatedContext(?string $context, ?string $whatHappenedBefore, ?string
    $whatUserWantsToUnderstand, ?string $backgroundInformation, ?string $initialInterpretation):
    self` — rebuilds via `new self(...)` with all fields, five newly validated via the same
    helper `create()` uses. Takes *final* values; the controller resolves "keep old" vs. "apply
    new" per field before calling.
  - **Bug fix, discovered while implementing this spec:** `withAddedNote()`/`withAddedTag()`
    rebuild via positional `new self(...)` calls that stopped at `$this->tags` — once the
    constructor gained the five new trailing params, those two methods would have silently
    dropped any already-set context fields back to their `null` default every time a note or tag
    was added (the new params would fall through to their constructor defaults instead of
    preserving `$this->context` etc.). Both methods now explicitly pass through all five current
    context values.
- **`SqliteConsultationRepository`**:
  - `upsertConsultation()`: five new bound params/columns in the `INSERT ... ON CONFLICT`.
  - `hydrate()`: five new `(string|null) $row['...']` reads, passed to `reconstitute()`.
- **`ConsultationController`**:
  - `create()`: parse the five optional keys via a shared private
    `parseOptionalContextField(mixed $value, string $key): ?string` (returns `null` if absent,
    the string if present-and-string, throws `\InvalidArgumentException` if present-and-not-a-
    string) — used for all five in both `create()` and `update()`.
  - `update()`: for each of the five keys, `array_key_exists($key, $body)` decides "touch it"
    (parse via the same helper, allowing `null` explicitly) vs. "leave `$consultation->$field`
    as-is." Builds the five final values, calls `withUpdatedContext()` once (only if at least one
    of the five — or note/tag — was actually present; matches REQ-CTX-005's "at least one" rule,
    extending the existing check rather than replacing it).
  - `toJson()`: five new keys.
- **Frontend**:
  - `entities/consultation/model.ts`: `Consultation` gains the five nullable fields;
    `NewConsultationRequest` gains them as optional; `ConsultationPatch` gains them as optional
    (`string | null` — distinct from `note`/`tag`'s shape since these support explicit clearing).
  - `entities/consultation/api.ts`: no new functions — `createConsultation`/`updateConsultation`
    already pass their `request`/`patch` argument straight through as the JSON body.
  - `NewConsultationPage.vue`: a `<details>` disclosure ("Add more context (optional)") wrapping
    five `<textarea>`s, each bound to a `ref<string>` defaulting to `''`; on submit, each maps to
    `trim() === '' ? undefined : value` so an untouched/emptied field isn't sent as an empty
    string (keeps `POST` bodies clean — omitting a key is more honest than sending `""`).
  - `ConsultationPage.vue`: a new section displaying any non-null context fields (labeled,
    similar to the Judgment/Image sections' style elsewhere in the app) plus an edit form
    (five textareas, "Save Context" button) that calls `updateConsultation` with only the changed
    fields — mirrors SPEC-013's note/tag form pattern (own `FormState`, scoped error, updates
    `state.value` in place on success).

## Architecture decisions

- **Five scalar fields, not a `ConsultationContext` value object.** Each field is independently
  settable/clearable via `PATCH`, with no shared invariant between them (unlike, say,
  `ConsultationNote`'s label+text+timestamp, which are only ever created together) — a wrapper
  object would just be a bag of optional strings with no behavior of its own, adding a layer
  without adding safety.
- **Explicit `array_key_exists()` in `update()` for the five new keys, not the existing `??`
  shorthand.** This is the first time this API needs to distinguish "key absent" from "key
  present with `null`" — `note`/`tag` never needed to (append-only, no clearing), so their
  existing `$body['note'] ?? null` shorthand stays as-is; only the five new keys get the more
  careful check.
- **`NewConsultationPage`'s context inputs collapsed behind a disclosure by default.** The
  question-first flow (SPEC-009) is this app's core loop — five extra textareas visible by
  default would bury it. A native `<details>` element needs no new JS state to implement.

## Affected areas

- `apps/api/database/migrations/2026_08_15_000001_add_consultation_context_fields.php` (new)
- `apps/api/src/Readings/Consultation.php`
- `apps/api/src/Readings/SqliteConsultationRepository.php`
- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/tests/Readings/ConsultationTest.php`
- `apps/api/tests/Readings/SqliteConsultationRepositoryTest.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/entities/consultation/model.ts`
- `apps/web/src/entities/consultation/api.spec.ts`
- `apps/web/src/pages/consultations/NewConsultationPage.vue`
- `apps/web/src/pages/consultations/NewConsultationPage.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`
- `apps/web/src/pages/consultations/ConsultationHistoryPage.spec.ts` (fixture update only, if it
  hand-builds a `Consultation`)

## Data / schema changes

Five new nullable `TEXT` columns on `consultations` — see "Data requirements" in spec.md.
Additive migration, no backfill needed.

## Risks / open questions

- None currently open.
