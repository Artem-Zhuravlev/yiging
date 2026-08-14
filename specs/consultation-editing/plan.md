# Plan — Consultation Notes & Tags Editing (SPEC-013)

**Depends on spec status:** `approved`

## Technical approach

Backend:

```
apps/api/src/Readings/ConsultationController.php   + update() action
apps/api/config/routes.php                          + PATCH /api/consultations/{id}
apps/api/tests/Readings/ConsultationControllerTest.php   + tests
```

- `ConsultationController::update(Request, array $vars)`: `findById()` first (404 if missing,
  matching REQ-EDIT-005), decode the body (reusing the existing `decodeJsonBody()`/
  `errorResponse()` helpers, no new parsing infrastructure), then:
  - if `note` present: build a `NoteLabel::tryFrom($body['note']['label'] ?? '')` (422 if
    invalid/missing) and `new ConsultationNote($label, $body['note']['text'] ?? '', $this->clock->now())`
    inside the same `try { } catch (\InvalidArgumentException $e) { 422 }` block `create()`
    already uses — `withAddedNote()` on the loaded `$consultation`.
  - if `tag` present: validate non-empty, `withAddedTag()`.
  - if neither present: `422`.
  - `$this->repository->save($consultation)`, respond `200` with `toJson($consultation)`
    (the same method `create()`/`show()` already use — no new serialization).

Frontend:

```
apps/web/src/entities/consultation/api.ts     + updateConsultation(id, patch)
apps/web/src/shared/api/http.ts                + apiPatch<T>()
apps/web/src/pages/consultations/ConsultationPage.vue   + note form, tag form
```

- `apiPatch<T>(path, body)`: same shape as the existing `apiPost`/`apiGet`, method `PATCH`.
- `updateConsultation(id, patch: {note?: {...}, tag?: string}): Promise<Consultation>`.
- `ConsultationPage.vue`: two more independent local states (`noteFormState`,
  `tagFormState`), same discriminated `idle | submitting | error` shape already established
  for the interpretation section (SPEC-010). On success, replace `state.consultation` in place
  (the loaded page state, not the whole page) with the response — no re-fetch, no reload,
  satisfying REQ-EDIT-007 directly.

## Architecture decisions

- **No new validation in the controller.** `ConsultationNote`'s constructor and
  `Consultation::withAddedTag()`/`withAddedNote()` already validate everything that needs
  validating (non-empty, max length, dedup) — REQ-EDIT-009 exists specifically so this endpoint
  doesn't grow a second copy of rules that already live in the domain layer, the same principle
  every prior controller in this codebase already follows.
- **One `PATCH`, two optional fields, not two separate endpoints.** A note and a tag are
  conceptually two different appendable things, but splitting them into
  `POST /api/consultations/{id}/notes` and `POST /api/consultations/{id}/tags` would be two new
  routes, two new controller methods, and two response-shape decisions for a feature this
  small — one `PATCH` with two optional keys covers both without that overhead, and matches the
  plan's own API list, which names exactly `PATCH /api/consultations/{id}` (singular), not two
  separate resources.
- **State replacement, not re-fetch, on the frontend.** The `PATCH` response already *is* the
  full updated consultation (REQ-EDIT-006, deliberately) specifically so the frontend never
  needs a follow-up `GET` — one round trip per note/tag addition, not two.
- **Empty-tag validation added here, not left as a pre-existing gap.** Nothing before this spec
  ever accepted arbitrary tag strings over HTTP (tags could previously only be set via
  `Consultation::create()`'s internal test/reconstitution paths, never from a request body) —
  this is the first time tag content arrives from outside the codebase, so it's the right place
  to add the guard, not a sign something was broken before.

## Affected areas

- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/Readings/ConsultationControllerTest.php`
- `apps/web/src/shared/api/http.ts` (+ test)
- `apps/web/src/entities/consultation/api.ts` (+ test)
- `apps/web/src/entities/consultation/model.ts` (patch request type)
- `apps/web/src/pages/consultations/ConsultationPage.vue` (+ test additions)

## Data / schema changes

None.

## Risks / open questions

- None currently open. Editing/removing existing notes/tags is named explicitly as deferred
  (see spec.md "Out of scope"), not silently dropped.
