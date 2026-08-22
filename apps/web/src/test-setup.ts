import { config } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import { appTheme } from './theme'
import { i18n } from './i18n'

// jsdom has no window.matchMedia; several PrimeVue components (Select's responsive overlay
// positioning, among others) call it unconditionally on mount.
if (!window.matchMedia) {
  window.matchMedia = (query: string): MediaQueryList =>
    ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    }) as MediaQueryList
}

// Every PrimeVue component reads its config from an app-level plugin install ($primevue
// injection) — without this, mounting any PrimeVue component in a test throws
// "Cannot read properties of undefined (reading 'config')".
config.global.plugins.push([
  PrimeVue,
  {
    theme: {
      preset: appTheme,
      options: { darkModeSelector: '.p-dark' },
    },
  },
])
config.global.plugins.push(i18n)
