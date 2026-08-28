# Tasks — Toast Notifications for Save Actions (SPEC-047)

- [x] **TASK-TOAST-001** — `shared/lib/useToastSuccess.ts`: `notifySaved(detailKey?)` over
      `useToast()` + `t()`, `severity: 'success'`, `life: 2500`. → REQ-TOAST-001
- [x] **TASK-TOAST-002** — `App.vue`: mount `<Toast position="bottom-right" />` once.
      → REQ-TOAST-001
- [x] **TASK-TOAST-003** — `test-setup.ts`: register `ToastService` globally. → REQ-TOAST-021
- [x] **TASK-TOAST-004** — `ConsultationPage.vue`: `notifySaved(...)` on the success branch of
      `saveContext` / `saveOutcome` / `addNote` / `addTag`, with a per-action detail key. Error
      handling untouched. → REQ-TOAST-002, 004
- [x] **TASK-TOAST-005** — `InterpretationSettingsPage.vue`: `notifySaved('settings.saved')` on
      `save` success. → REQ-TOAST-003, 004
- [x] **TASK-TOAST-006** — i18n: `toast.saved`, `consultationPage.contextSaved` / `.outcomeSaved`
      / `.noteAdded` / `.tagAdded`, `settings.saved` (en + uk). → REQ-TOAST-020
- [x] **TASK-TOAST-007** — `npm run verify` green; existing `ConsultationPage` / settings specs
      pass; browser pass (a toast on each of the five saves; inline error + no toast on a
      failing save); fill `plan.md` note; flip `spec.md` → `implemented`; add SPEC-047 to both
      README tables. → REQ-TOAST-022