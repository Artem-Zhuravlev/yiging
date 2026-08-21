import { definePreset } from '@primevue/themes'
import Aura from '@primevue/themes/aura'

/** Red-toned Aura preset (SPEC-037's UI overhaul) — PrimeVue ships a built-in `red` primitive
 * palette; this just wires it up as the semantic `primary` color instead of Aura's default
 * blue/emerald.
 *
 * Deliberately pinned to `primevue@^4.5.5` / `@primevue/themes@^4.5.4` (MIT), not npm's `latest`
 * (`primevue@5`) — as of this session, `primevue@5`/`@primeuix/themes` require a paid "PrimeUI"
 * license key and render an "Invalid License" watermark without one. `v4-stable` is the last
 * fully free major and remains fully functional, just no longer receiving new features. */
export const redTheme = definePreset(Aura, {
  semantic: {
    primary: {
      50: '{red.50}',
      100: '{red.100}',
      200: '{red.200}',
      300: '{red.300}',
      400: '{red.400}',
      500: '{red.500}',
      600: '{red.600}',
      700: '{red.700}',
      800: '{red.800}',
      900: '{red.900}',
      950: '{red.950}',
    },
  },
})
