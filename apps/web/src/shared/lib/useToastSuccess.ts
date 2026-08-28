import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'

/**
 * One-liner success toasts for "save" actions that otherwise confirm nothing (SPEC-047).
 * Errors stay inline — this is only for the happy path.
 */
export function useToastSuccess() {
  const toast = useToast()
  const { t } = useI18n()

  return {
    /** `detailKey` is an i18n key for the second line ("Context saved" etc.); omit for a bare "Saved". */
    notifySaved(detailKey?: string): void {
      toast.add({
        severity: 'success',
        summary: t('toast.saved'),
        detail: detailKey ? t(detailKey) : undefined,
        life: 2500,
      })
    },
  }
}
