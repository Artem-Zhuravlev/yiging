# Plan — Toast Notifications for Save Actions (SPEC-047)

## Files

### New
- `apps/web/src/shared/lib/useToastSuccess.ts`
  ```ts
  import { useToast } from 'primevue/usetoast'
  import { useI18n } from 'vue-i18n'

  export function useToastSuccess() {
    const toast = useToast()
    const { t } = useI18n()
    return {
      notifySaved(detailKey?: string) {
        toast.add({
          severity: 'success',
          summary: t('toast.saved'),
          detail: detailKey ? t(detailKey) : undefined,
          life: 2500,
        })
      },
    }
  }
  ```

### Changed
- `apps/web/src/App.vue` — `import Toast from 'primevue/toast'`; add
  `<Toast position="bottom-right" />` next to `<LiveRegion />` (outside the `print-hidden`
  toolbar; a toast on a printout is meaningless but harmless — leave default).
- `apps/web/src/test-setup.ts` — `import ToastService from 'primevue/toastservice'` and
  `config.global.plugins.push(ToastService)` (after the PrimeVue plugin push).
- `apps/web/src/pages/consultations/ConsultationPage.vue`
  - `const { notifySaved } = useToastSuccess()` in `<script setup>`.
  - `saveContext` success branch → `notifySaved('consultationPage.contextSaved')`.
  - `saveOutcome` success branch → `notifySaved('consultationPage.outcomeSaved')`.
  - `addNote` success branch → `notifySaved('consultationPage.noteAdded')`.
  - `addTag` success branch → `notifySaved('consultationPage.tagAdded')`.
  - No other changes.
- `apps/web/src/pages/settings/InterpretationSettingsPage.vue`
  - `const { notifySaved } = useToastSuccess()`; `save` success branch →
    `notifySaved('settings.saved')`.
- `apps/web/src/i18n/locales/{en,uk}.ts`
  - `toast: { saved: 'Saved' / 'Збережено' }`
  - `consultationPage.contextSaved` = 'Context saved' / 'Контекст збережено'
  - `consultationPage.outcomeSaved` = 'Outcome saved' / 'Результат збережено'
  - `consultationPage.noteAdded` = 'Note added' / 'Нотатку додано'
  - `consultationPage.tagAdded` = 'Tag added' / 'Тег додано'
  - `settings.saved` = 'Interpretation profile saved' / 'Профіль інтерпретації збережено'

## Testing
- `test-setup.ts` change makes existing specs mount fine.
- `ConsultationPage.spec.ts` — no assertion changes required (toasts teleport, don't affect
  `wrapper.text()` of the page). Optionally add: after a successful `saveContext`, spy on
  `primevue/usetoast`'s `add` — but PrimeVue's `useToast` uses a shared event bus; simplest is
  a light check that the save still succeeds (state → idle) which the existing test already
  covers. Add one small test that mounting doesn't throw with ToastService present (implicit).
- `InterpretationSettingsPage.spec.ts` — same; existing "saves and returns to idle" test still
  green.
- New: `useToastSuccess` isn't unit-tested in isolation (it's a 6-line wrapper over
  `useToast`); its behaviour is exercised through the pages.

## Verify

`npm run verify`; browser: on `/consultations/:id` do Save Context / Save Outcome / Add Note /
Add Tag and see a toast each time; on `/settings` Save and see a toast; force a save error
(e.g. offline) and confirm the inline error shows and no toast.

## Verification note (2026-08-28)

- `npm run verify` green (web 190 tests; api 312; yijing-core 55). `test-setup.ts` now registers
  `ToastService`; existing ConsultationPage / settings specs pass unchanged.
- Live pass: "Save" on /settings shows a success toast ("Збережено / Профіль інтерпретації
  збережено", `p-toast-message-success`) that auto-dismisses after ~2.5s; "Add Tag" on a
  consultation shows "Збережено / Тег додано". Errors still render inline as before.
