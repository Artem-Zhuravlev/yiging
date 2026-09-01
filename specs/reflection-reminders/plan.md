# Plan — Reflection Reminders (SPEC-054)

## 1. Database

- `apps/api/database/migrations/2026_09_01_000001_create_consultation_reminders.php`:
  ```sql
  CREATE TABLE consultation_reminders (
      consultation_id TEXT PRIMARY KEY
          REFERENCES consultations(id) ON DELETE CASCADE,
      remind_at  TEXT NOT NULL,
      created_at TEXT NOT NULL
  );
  CREATE INDEX idx_consultation_reminders_remind_at ON consultation_reminders (remind_at);
  ```
  (Same `'up' => '…'` array shape as the other migrations; multiple statements separated by
  `;`, as in `create_journal_entries`.)

## 2. API

### `App\Readings\DueReminder` (new, readonly)
- `id, question`, `primaryKingWenNumber/ChineseName/Pinyin`,
  `resultingKingWenNumber/ChineseName/Pinyin`, `remindAtAtom`, `createdAtAtom`.
- `toJson()` → `{ id, question, primaryHexagram:{kingWenNumber,chineseName,pinyin},
  resultingHexagram:{…}, remindAt, createdAt }`.

### `App\Readings\ConsultationReminderRepository` (new interface)
- `set(string $consultationId, \DateTimeImmutable $remindAt, \DateTimeImmutable $createdAt): void`
- `clear(string $consultationId): void`
- `findRemindAt(string $consultationId): ?\DateTimeImmutable`
- `findDue(\DateTimeImmutable $now): list<DueReminder>`

### `App\Readings\SqliteConsultationReminderRepository` (new)
- `set` — `INSERT … ON CONFLICT(consultation_id) DO UPDATE SET remind_at = excluded.remind_at`
  (keep original `created_at`). Store `remind_at`/`created_at` as `DATE_ATOM`.
- `findDue` —
  ```sql
  SELECT r.consultation_id, r.remind_at, r.created_at,
         c.question, c.primary_king_wen_number, c.resulting_king_wen_number
  FROM consultation_reminders r
  JOIN consultations c ON c.id = r.consultation_id
  LEFT JOIN consultation_outcomes o ON o.consultation_id = c.id
  WHERE o.consultation_id IS NULL AND r.remind_at <= :now
  ORDER BY r.remind_at ASC
  ```
  hexagram names via `Hexagram::fromKingWenNumber(...)` (as `toListItem` does).
- `remind_at` is stored `DATE_ATOM`; string `<=` comparison is safe because every value is the
  same fixed-width ATOM format (same assumption `findListPage`'s cursor uses on `created_at`).

### `ConsultationController`
- ctor: add `$this->reminderRepository = new SqliteConsultationReminderRepository($pdo)` (reuse
  the same `Database::connect($config)` handle the consultation repo gets).
- `reminders(Request): Response` → `new JsonResponse(array_map(fn (DueReminder $d) => $d->toJson(),
  $this->reminderRepository->findDue($this->clock->now())))`.
- `setReminder(Request $request, array $vars): Response`:
  - `findById` → `404` if null.
  - decode body; `$raw = $body['remindAt'] ?? null`; if not a non-empty string → `422`.
  - `try { $remindAt = new \DateTimeImmutable($raw); } catch (\Exception) { 422 }`.
  - `$this->reminderRepository->set($vars['id'], $remindAt, $this->clock->now())`.
  - `new JsonResponse(['remindAt' => $remindAt->format(DATE_ATOM)])`.
- `clearReminder(Request $request, array $vars): Response`:
  - `findById` → `404` if null; else `->clear(...)`; `new Response('', 204)`.
- `toJsonWithRepeats()` — append
  `'reminder' => ($at = $this->reminderRepository->findRemindAt($consultation->id)) === null
     ? null : ['remindAt' => $at->format(DATE_ATOM)]`.
- `update()` — after `$this->repository->save($consultation)`, add
  `if ($touchesAnyOutcomeField) { $this->reminderRepository->clear($vars['id']); }`.

### Routes (`apps/api/config/routes.php`)
```php
$r->addRoute('GET', '/api/consultations/reminders', [ConsultationController::class, 'reminders']);
// … existing …
$r->addRoute('PUT',    '/api/consultations/{id}/reminder', [ConsultationController::class, 'setReminder']);
$r->addRoute('DELETE', '/api/consultations/{id}/reminder', [ConsultationController::class, 'clearReminder']);
```
`/reminders` goes next to the other literal `/api/consultations/...` routes, all before the
`/{id}` catch (FastRoute prefers static segments, but keep the source order tidy anyway).

### Tests
- `apps/api/tests/Readings/SqliteConsultationReminderRepositoryTest.php` — set + read back;
  `set` twice replaces the date and keeps `created_at`; `clear`; `findDue` returns only
  past-due + outcome-less, ordered by `remind_at`; a due row for a consultation that gains an
  outcome drops out.
- `apps/api/tests/Readings/ConsultationControllerTest.php` — new cases:
  - `testPutReminderThenShowRoundTrips` (PUT `2026-09-15`, GET `/{id}` → `reminder.remindAt`
    starts `2026-09-15`).
  - `testDeleteReminderClearsIt` (PUT, DELETE → `204`, GET → `reminder` null).
  - `testRemindersEndpointFiltersByDueAndOutcome` — 3 consultations: past-due no outcome
    (present), `remind_at` far future (absent), past-due then PATCH an outcome (absent);
    assert ordering on two past-due.
  - `testPutReminderMissingConsultationIs404`, `testPutReminderMalformedDateIs422`.
  - `testRecordingOutcomeClearsReminder`.
  - `testListEndpointHasNoReminderKey`.
  - helper: a `putReminder($id, $date)` / `getJson` following the file's existing style.

## 3. Frontend

### `entities/consultation/model.ts`
```ts
export interface ReflectionReminder { remindAt: string }
export interface DueReminder {
  id: string
  question: string
  primaryHexagram: HexagramSummary
  resultingHexagram: HexagramSummary
  remindAt: string
  createdAt: string
}
// ConsultationDetail:
reminder?: ReflectionReminder | null
```

### `entities/consultation/api.ts`
```ts
export function fetchDueReminders(): Promise<DueReminder[]> {
  return apiGet<DueReminder[]>('/consultations/reminders')
}
export function setReflectionReminder(id: string, remindAt: string): Promise<ReflectionReminder> {
  return apiPut<ReflectionReminder>(`/consultations/${id}/reminder`, { remindAt })
}
export function clearReflectionReminder(id: string): Promise<void> {
  return apiDelete(`/consultations/${id}/reminder`)
}
```
(`apiPut` — check `shared/api/http`; add a thin wrapper next to `apiPatch` if absent.)

### `ConsultationPage.vue`
- refs: `reminderDate = ref('')` (bound to the date input), `reminder = ref<ReflectionReminder
  | null>(null)` set from `consultation.reminder` in `onMounted`.
- Template block, rendered `v-if="!outcomeRecorded"` inside/next to the outcome section:
  - no reminder: `<label>` + `<input type="date" v-model="reminderDate">` + `<Button
    :label="t('reminders.set')" :disabled="!reminderDate" @click="saveReminder">`.
  - reminder set: `{{ t('reminders.remindOn', { date: formatDate(reminder.remindAt) }) }}` +
    Change (reveals the input again) + Clear.
- `saveReminder()` → `await setReflectionReminder(id, reminderDate.value)`; set `reminder.value`;
  `notifySaved('reminders.saved')`. `clearReminder()` → `await clearReflectionReminder(id)`;
  `reminder.value = null`; `notifySaved()`.
- Errors surface through the page's existing inline error pattern.

### `HomePage.vue`
- `dueReminders = ref<DueReminder[]>([])`; in `onMounted`, `fetchDueReminders().then(r =>
  dueReminders.value = r).catch(() => {})`.
- `<section v-if="dueReminders.length > 0">` after the "recent" section: heading
  `t('reminders.dueTitle')`, a `<ul>` of `<router-link :to="`/consultations/${r.id}`">` each
  showing the question + primary→resulting + `t('reminders.overdueBy', { days: overdueDays(r) })`,
  and a `<Button size="small" text :label="t('reminders.snooze')" @click="snooze(r)">`.
- `snooze(r)` → date string for `new Date(Date.now() + 7*864e5)` (`toISOString().slice(0,10)`),
  `await setReflectionReminder(r.id, date)`, then `dueReminders.value = dueReminders.value
  .filter(x => x.id !== r.id)`.
- `overdueDays(r)` = `Math.max(0, Math.floor((Date.now() - Date.parse(r.remindAt)) / 864e5))`.

### i18n (`en.ts` + `uk.ts`) — `reminders` block
`dueTitle` "Due for reflection", `remindMe` "Remind me to record the outcome",
`set` "Set reminder", `change` "Change", `clear` "Clear", `snooze` "Snooze 1 week",
`remindOn` "Reminder set for {date}", `overdueBy` "{days}d overdue" / "due today" handled by
`overdueDays === 0`, `saved` "Reminder updated", `help` "The app will surface this on the home
page once the date arrives — no notifications."

### Tests
- `entities/consultation/api.spec.ts` — add `reminder: null` to the detail fixture; tests for
  `fetchDueReminders`, `setReflectionReminder`, `clearReflectionReminder` hitting the right
  method + path.
- `pages/consultations/ConsultationPage.spec.ts` — fixture `reminder: null`; a test that the
  date input shows when there is no outcome and calling it invokes `setReflectionReminder`;
  a test that with an outcome present the control is absent.
- `pages/home/HomePage.spec.ts` — extend the `entities/consultation/api` mock with
  `fetchDueReminders`; a test: one due reminder → "Due for reflection" section + a snooze
  button that calls `setReflectionReminder`.

## 4. Verify

`cd apps/api && composer test && composer stan && composer lint`; `npm run verify`.
Browser (php dev server + web): detail page of an outcome-less consultation → set a date →
reload → reminder shows; Home → "Due for reflection" lists it (use a past date) → Snooze →
row disappears; record an outcome → reminder control gone, Home section empty.

## Verification note (2026-09-01)

- apps/api: 344 tests (13 new — `SqliteConsultationReminderRepositoryTest` ×6,
  `ConsultationControllerTest` ×7), phpstan level 8 + php-cs-fixer clean.
- `npm run verify` green: web lint/typecheck/test (incl. `api.spec.ts` ×3, `HomePage.spec.ts`
  ×2, `ConsultationPage.spec.ts` ×2 new) + build; api; yijing-core 75.
- Live pass (php dev server): `PUT /api/consultations/{id}/reminder` stores and round-trips on
  `GET /{id}` (`reminder.remindAt`); `GET /api/consultations/reminders` returns the due entry
  with hexagram names; `PATCH` with an outcome field clears it from both `/reminders` and
  `reminder` on the detail response; `422` on a malformed date, `404` on an unknown
  consultation, `204` on `DELETE`; `GET /api/consultations` (list) has no `reminder` key.
- Migration `2026_09_01_000001_create_consultation_reminders` applied to the dev DB via
  `php scripts/migrate.php`.
