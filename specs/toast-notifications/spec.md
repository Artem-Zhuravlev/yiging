# SPEC-047 — Toast Notifications for Save Actions

**Status:** implemented
**Owner:** unassigned
**Last updated:** 2026-08-28

## Problem

`main.ts` installs PrimeVue's `ToastService` but nothing uses it — there is no `<Toast>`
component mounted and no `useToast()` call anywhere. As a result several "save" actions give no
success feedback: on the consultation detail page, "Save Context" and "Save Outcome" just
return the button to its resting label; on `/settings`, "Save" does the same. The user can't
tell whether the click did anything. (Errors *are* surfaced — inline `Message`s — and stay
that way.)

## Purpose

Mount a single app-wide `<Toast>` and fire a brief success toast from the actions that
currently confirm nothing: save context, save outcome, add note, add tag on the consultation
page, and save profile on the settings page. Errors stay inline; nothing else changes.

## Scope

- `App.vue`: mount `<Toast position="bottom-right" />` once (PrimeVue teleports it; it carries
  its own `aria-live` region).
- A tiny `shared/lib/useToastSuccess.ts` helper: `useToastSuccess()` returns
  `notifySaved(detailKey?)` which calls `toast.add({ severity: 'success', summary: t('toast.saved'),
  detail: detailKey ? t(detailKey) : undefined, life: 2500 })`. Keeps every call site to one
  line and the copy consistent.
- `ConsultationPage.vue`: on the success branch of `saveContext`, `saveOutcome`, `addNote`,
  `addTag` → `notifySaved()` (with a specific `detail` per action, e.g. "Context saved",
  "Outcome saved", "Note added", "Tag added"). The existing state resets and inline error
  handling are untouched.
- `InterpretationSettingsPage.vue`: on the success branch of `save` → `notifySaved('settings.saved')`.
- `test-setup.ts`: register `ToastService` globally so components calling `useToast()` mount in
  tests without throwing.
- Localised (en + uk): `toast.saved` ("Saved" / "Збережено"), plus per-action detail strings
  under `consultationPage.*` / `settings.*` where a specific one reads better
  (`consultationPage.contextSaved`, `.outcomeSaved`, `.noteAdded`, `.tagAdded`,
  `settings.saved`).

## Out of scope

- **Converting error `Message`s to toasts.** Errors must persist next to the control that
  failed; they stay inline.
- **Toasts for favorite toggles, copy-share-link, interpretation fetch, casting, import
  backup, journal add.** Favorite/copy already change a visible label; import already shows an
  inline success `Message` (SPEC-028 tests assert on it); the rest are out of this pass's
  scope. A later spec can extend coverage.
- **A toast queue policy / dedup / positioning options / a global error boundary.**
- **Any change to what the save endpoints do or return.**

## Functional requirements

- **REQ-TOAST-001** — A single `<Toast>` is mounted app-wide; success toasts auto-dismiss
  after ~2.5s.
- **REQ-TOAST-002** — Saving context, saving outcome, adding a note, and adding a tag on the
  consultation page each show a success toast on success; a failure shows the existing inline
  error and no toast.
- **REQ-TOAST-003** — Saving the interpretation profile on `/settings` shows a success toast on
  success; a failure shows the existing inline error and no toast.
- **REQ-TOAST-004** — Toasts do not replace or remove any existing inline error UI.

## Non-functional requirements

- **REQ-TOAST-020** — New strings localised (en + uk).
- **REQ-TOAST-021** — `test-setup.ts` registers `ToastService`; existing `ConsultationPage` /
  `InterpretationSettingsPage` specs still pass.
- **REQ-TOAST-022** — `npm run verify` passes.

## Data requirements

None.

## API requirements

None.

## Edge cases

- Rapid double-save → two toasts stack (PrimeVue default). Acceptable.
- Save fails → `notifySaved` is never reached (it's on the success branch only); inline error
  shows as today.
- `useToast()` outside a `ToastService`-provided tree → only possible in a test without the
  plugin; `test-setup.ts` fix covers it.

## Acceptance criteria

- [x] After "Save Context" / "Save Outcome" / "Add Note" / "Add Tag" succeeds, a success toast
      appears and fades — verified live for "Add Tag" ("Збережено / Тег додано") and for
      `/settings` "Save" ("Збережено / Профіль інтерпретації збережено", auto-dismissed ~2.5s).
- [x] A failing save shows the same inline error and no toast — `notifySaved` is only on the
      success branch; error branches are unchanged.
- [x] `npm run verify` passes (web 190, api 312, yijing-core 55); the consultation and settings
      specs are green (`test-setup.ts` now registers `ToastService`).

## Implementation note (2026-08-28)

- `shared/lib/useToastSuccess.ts` — `notifySaved(detailKey?)` over `useToast()` + `t()`,
  `severity: 'success'`, `life: 2500`. `App.vue` mounts one `<Toast position="bottom-right" />`.
  `test-setup.ts` registers `ToastService` globally.
- `ConsultationPage.vue`: `notifySaved(...)` on the success branch of `saveContext` /
  `saveOutcome` / `addNote` / `addTag`. `InterpretationSettingsPage.vue`: on `save` success.
  Error branches untouched — inline `Message`s stay.
- i18n: `toast.saved`, `consultationPage.{contextSaved,outcomeSaved,noteAdded,tagAdded}`,
  `settings.saved` (en + uk).
