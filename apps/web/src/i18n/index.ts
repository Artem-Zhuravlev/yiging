import { createI18n } from 'vue-i18n'
import { en } from './locales/en'
import { uk } from './locales/uk'

export const SUPPORTED_LOCALES = ['en', 'uk'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

const STORAGE_KEY = 'yijing-locale'

function isSupportedLocale(value: string | null): value is Locale {
  return value !== null && (SUPPORTED_LOCALES as readonly string[]).includes(value)
}

// Browser language wins only when it's one of our supported locales (specifically Ukrainian);
// everything else falls back to English, never a silently-blank UI for an unsupported language.
function detectInitialLocale(): Locale {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (isSupportedLocale(stored)) {
    return stored
  }

  const browserLanguage = navigator.language.slice(0, 2)
  return browserLanguage === 'uk' ? 'uk' : 'en'
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, uk },
})

export function setLocale(locale: Locale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
}

document.documentElement.lang = i18n.global.locale.value
