import { definePreset } from '@primevue/themes'
import Aura from '@primevue/themes/aura'

/** Blue-toned Aura preset (SPEC-037's UI overhaul) — PrimeVue ships a built-in `blue` primitive
 * palette; this just wires it up as the semantic `primary` color. Originally red, per the user's
 * initial request; swapped to blue after they found red uncomfortable to look at (2026-08-22).
 *
 * Deliberately pinned to `primevue@^4.5.5` / `@primevue/themes@^4.5.4` (MIT), not npm's `latest`
 * (`primevue@5`) — as of this session, `primevue@5`/`@primeuix/themes` require a paid "PrimeUI"
 * license key and render an "Invalid License" watermark without one. `v4-stable` is the last
 * fully free major and remains fully functional, just no longer receiving new features. */
export const appTheme = definePreset(Aura, {
  semantic: {
    primary: {
      50: '{blue.50}',
      100: '{blue.100}',
      200: '{blue.200}',
      300: '{blue.300}',
      400: '{blue.400}',
      500: '{blue.500}',
      600: '{blue.600}',
      700: '{blue.700}',
      800: '{blue.800}',
      900: '{blue.900}',
      950: '{blue.950}',
    },
  },
})
