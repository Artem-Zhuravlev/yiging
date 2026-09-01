# Tasks — Reflection Reminders (SPEC-054)

## Database

- [x] **TASK-RR-001** — migration `2026_09_01_000001_create_consultation_reminders.php`
      (table + `remind_at` index, FK cascade). → REQ-RR-020, data

## API

- [x] **TASK-RR-002** — `App\Readings\DueReminder` readonly + `toJson()`. → REQ-RR-003
- [x] **TASK-RR-003** — `ConsultationReminderRepository` interface + `Sqlite…` impl
      (`set` upsert keeping `created_at`, `clear`, `findRemindAt`, `findDue` with the
      outcome-less + past-due filter, `remind_at ASC`). → REQ-RR-001..003
- [x] **TASK-RR-004** — `SqliteConsultationReminderRepositoryTest`: set/replace/find/clear,
      `findDue` filtering + ordering, outcome drops a row. → REQ-RR-021
- [x] **TASK-RR-005** — `ConsultationController`: `reminders()`, `setReminder()`,
      `clearReminder()`; `reminder` in `toJsonWithRepeats()`; outcome-touch clears the
      reminder in `update()`. → REQ-RR-001..005
- [x] **TASK-RR-006** — routes: `GET /api/consultations/reminders`,
      `PUT|DELETE /api/consultations/{id}/reminder`. → REQ-RR-001..004
- [x] **TASK-RR-007** — `ConsultationControllerTest`: PUT→show round-trip, DELETE clears,
      `/reminders` due+outcome filtering & ordering, 404 unknown consultation, 422 bad date,
      outcome PATCH clears, list endpoint has no `reminder`. → REQ-RR-001..005, 021

## Frontend

- [x] **TASK-RR-008** — `entities/consultation/model.ts`: `ReflectionReminder`, `DueReminder`;
      `ConsultationDetail.reminder?`. `api.ts`: `fetchDueReminders`, `setReflectionReminder`,
      `clearReflectionReminder` (+ `apiPut` in `shared/api/http` if missing). → REQ-RR-004
- [x] **TASK-RR-009** — `ConsultationPage.vue`: reminder control (date input + Set / Change /
      Clear), only while no outcome; success toast. → REQ-RR-006
- [x] **TASK-RR-010** — `HomePage.vue`: "Due for reflection" section with overdue text +
      "Snooze 1 week"; hidden when empty / on fetch failure. → REQ-RR-007
- [x] **TASK-RR-011** — i18n `reminders.*` (en + uk). → REQ-RR-022
- [x] **TASK-RR-012** — specs: `api.spec.ts` (3 new fns + `reminder: null` fixture),
      `ConsultationPage.spec.ts` (control shown/hidden, calls api), `HomePage.spec.ts`
      (due section + snooze). → REQ-RR-022

## Close-out

- [x] **TASK-RR-013** — `composer test`/`stan`/`lint` + `npm run verify` green; browser pass
      (set → reload → shows; Home lists + snooze; outcome hides control); fill `plan.md` note;
      flip `spec.md` → `implemented`; add SPEC-054 row to both README tables. → REQ-RR-021, 022
