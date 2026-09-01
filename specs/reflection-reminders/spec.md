# SPEC-054 — Reflection Reminders

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-09-01

## Problem

A consultation's value compounds when the querent comes back weeks later and records what
actually happened (the outcome, SPEC-020) — that is where the practice becomes a feedback loop
rather than a diary. In reality the querent forgets. The app never nudges. Consultations without
an outcome just sink down the History list.

There is no reminder mechanism at all, and the two obvious ones are out of reach or unwanted:
a background job / email needs server infrastructure the project does not have, and a PWA / push
notification was explicitly ruled out for this iteration.

## Purpose

Let the querent set a per-consultation date — "remind me to record the outcome on 15 Sept" —
and surface the ones that have come due, in-app, on the Home dashboard the next time they open
the app. No background processing, no notifications: the reminder is a stored date the app reads
and renders on load.

## Scope

### Data — a new side table, `Consultation` untouched

`consultation_reminders (consultation_id TEXT PRIMARY KEY, remind_at TEXT NOT NULL,
created_at TEXT NOT NULL)`, `consultation_id` FK → `consultations(id)` `ON DELETE CASCADE`. At
most one reminder per consultation. This is a peripheral concern with its own lifecycle
(set / change / snooze / auto-clear on outcome) — modelled as its own table and repository, the
way favourite hexagrams are (SPEC-031), **not** as a nullable column on the hot `consultations`
table or a new field threaded through the immutable `Consultation` value object.

### API

- `PUT /api/consultations/{id}/reminder` — body `{ "remindAt": "2026-09-15" }` (a date, or a
  full ISO-8601 instant). Upserts the reminder. `404` if the consultation does not exist,
  `422` on a missing / unparseable `remindAt`. Returns `{ "remindAt": "<ISO-8601>" }`.
- `DELETE /api/consultations/{id}/reminder` — clears it. `404` if the consultation does not
  exist; `204` otherwise (idempotent — no error when there was no reminder).
- `GET /api/consultations/reminders` — the **due** list: every reminder whose `remind_at <= now`
  **and** whose consultation has no recorded outcome, oldest `remind_at` first. Each entry:
  `{ id, question, primaryHexagram: {kingWenNumber, chineseName, pinyin}, resultingHexagram:
  {…}, remindAt, createdAt }`. A lean read model (`DueReminder`), not a full `Consultation`
  hydration.
- `GET /api/consultations/{id}` gains `reminder: { remindAt } | null` — detail-endpoint only,
  the same rule `repeats` (SPEC-023) and `readingGuidance` (SPEC-052) follow. The list endpoint
  `GET /api/consultations` is unchanged.
- Recording an outcome (`PATCH /api/consultations/{id}` with any outcome field) clears any
  reminder on that consultation — its job is done.

### Frontend

- `entities/consultation/model.ts`: `ReflectionReminder { remindAt: string }`,
  `DueReminder { id, question, primaryHexagram, resultingHexagram, remindAt, createdAt }`;
  `ConsultationDetail` gains `reminder?: ReflectionReminder | null` (optional — existing
  `Consultation` / `ConsultationDetail` fixtures are not forced to change).
- `entities/consultation/api.ts`: `fetchDueReminders()`, `setReflectionReminder(id, remindAt)`,
  `clearReflectionReminder(id)`.
- `ConsultationPage.vue`: in the outcome area, shown only while the consultation has **no**
  outcome yet — a "Reflection reminder" row: a native `<input type="date">` plus a Set button;
  once a reminder exists, its date is shown with Change / Clear. Saving / clearing fires the
  standard success toast (SPEC-047).
- `HomePage.vue`: a "Due for reflection" section (hidden when the list is empty or the fetch
  fails, like the other secondary sections — SPEC-045) listing the due reminders, each linking
  to its consultation and showing how overdue it is, with a "Snooze 1 week" button that pushes
  the reminder out 7 days and drops the row.
- Localised (en + uk): section titles, the control labels, the overdue phrase, the toast.

## Out of scope

- **Push notifications, service worker, PWA, email, any background job.** The reminder is only
  ever read on a normal page load. (Explicitly deferred by the user for this iteration.)
- **Automatic / default reminder dates** ("remind me in 2 weeks" on every cast). Opt-in only —
  a reminder exists solely because the querent set one.
- **A reminder count badge on the nav / History link.** The due list lives on Home only; adding
  a cross-page badge means fetching the count on every route.
- **Reminders for anything other than "record the outcome"** (e.g. "revisit this reading"),
  recurring reminders, or multiple reminders per consultation.
- **Time-of-day / timezone precision.** `remind_at` is compared to the server clock; a date is
  treated as due from its start. Good enough for a "you meant to reflect on this" nudge.
- Changing the History list payload or `ConsultationListItem`.

## Functional requirements

- **REQ-RR-001** — `PUT /api/consultations/{id}/reminder` upserts one reminder for an existing
  consultation from a `remindAt` that is a date (`YYYY-MM-DD`) or a full ISO-8601 instant;
  `404` when the consultation is unknown, `422` when `remindAt` is absent or unparseable.
- **REQ-RR-002** — `DELETE /api/consultations/{id}/reminder` removes the reminder if present
  and returns `204`; `404` only when the consultation itself is unknown; calling it with no
  reminder set is not an error.
- **REQ-RR-003** — `GET /api/consultations/reminders` returns exactly the reminders with
  `remind_at <= now` whose consultation has **no** `consultation_outcomes` row, ordered by
  `remind_at` ascending, each as the `DueReminder` shape.
- **REQ-RR-004** — `GET /api/consultations/{id}` includes `reminder` (`{ remindAt }` or
  `null`); `GET /api/consultations` does not.
- **REQ-RR-005** — A `PATCH /api/consultations/{id}` that touches any outcome field deletes
  that consultation's reminder.
- **REQ-RR-006** — The consultation detail page lets a querent set, change, and clear a
  reflection reminder while no outcome is recorded, and hides the control once one is.
- **REQ-RR-007** — The Home dashboard shows the due reminders with an overdue indication and a
  working "Snooze 1 week" action; the section is absent when there are none.

## Non-functional requirements

- **REQ-RR-020** — The reminder table/repository is separate; `App\Readings\Consultation` and
  its persistence are not modified.
- **REQ-RR-021** — `phpstan` level 8 + `php-cs-fixer` clean in `apps/api`; the new repository
  is unit-tested against SQLite (set, replace, find, clear, due-filtering + ordering).
- **REQ-RR-022** — New UI strings localised (en + uk); `npm run verify` passes; pre-existing
  `Consultation` / `ConsultationDetail` fixtures stay green (the model field is optional).

## Data requirements

- New migration `create_consultation_reminders` — the table above, plus an index on
  `remind_at` for the due query.
- No change to `consultations`, `consultation_outcomes`, or any existing table.

## API requirements

- `PUT /api/consultations/{id}/reminder` → `{ remindAt }` | `404` | `422`.
- `DELETE /api/consultations/{id}/reminder` → `204` | `404`.
- `GET /api/consultations/reminders` → `DueReminder[]`.
- `GET /api/consultations/{id}` → response gains `reminder: { remindAt } | null`.
- Route order: `/api/consultations/reminders` is registered before `/api/consultations/{id}`.

## Edge cases

- A reminder whose consultation is later deleted disappears with it (`ON DELETE CASCADE`).
- `PUT` on a consultation that already has a reminder replaces the date (upsert on the PK).
- A reminder dated in the past is accepted and is immediately "due".
- Recording an outcome, then reopening the detail page: no reminder control (outcome present),
  and `reminder` is `null` (REQ-RR-005 cleared it).
- `GET /api/consultations/reminders` with none due → `[]`.
- Snooze on Home computes "now + 7 days" client-side and `PUT`s it, then removes the row
  locally without a refetch.

## Acceptance criteria

- [x] `PUT` then `GET /api/consultations/{id}` round-trips `reminder.remindAt`; `DELETE` then
      `GET` shows `reminder: null`.
- [x] `GET /api/consultations/reminders` includes a past-due outcome-less consultation and
      excludes a future one and a past-due one that has an outcome.
- [x] Recording an outcome via `PATCH` clears the reminder.
- [x] The detail page shows the reminder control only before an outcome; Home shows a "Due for
      reflection" section with a working snooze and hides it when empty.
- [x] `npm run verify` passes; existing consultation fixtures are untouched.
