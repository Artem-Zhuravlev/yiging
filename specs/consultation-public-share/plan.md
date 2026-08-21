# Plan — Consultation Public Share Link (SPEC-029)

**Depends on spec status:** `approved`

## Technical approach

- `router/index.ts`: new route `{ path: '/share/consultations/:id', name: 'consultation-share',
  meta: { public: true }, component: () => import('../pages/consultations/SharedConsultationPage.vue') }`.
- `App.vue`: `const route = useRoute()`; `<nav v-if="!route.meta.public" ...>` (existing nav,
  unchanged) — `<span v-else class="px-6 py-4 text-sm font-medium">Yijing</span>` when public,
  keeping the same vertical rhythm without any clickable link.
- `pages/consultations/SharedConsultationPage.vue`: same `State` union / `onMounted` /
  `fetchHexagram`-for-lines-marking pattern as `ConsultationPage.vue`, but built fresh (not a
  variant of the existing component) since its render tree genuinely excludes more than half of
  what `ConsultationPage` renders — a shared component parameterized by "am I in share mode"
  would need `v-if`s wrapping nearly every section, which is harder to audit for "does this leak
  data" than a separate, deliberately minimal template. Only reads
  `consultation.{question, method, createdAt, primaryHexagram, resultingHexagram,
  changingLinePositions, notes, tags, context, whatHappenedBefore, whatUserWantsToUnderstand,
  backgroundInformation, initialInterpretation, outcome}` — never touches
  `.followUpTo`/`.followUps`/`.repeats` (present on the fetched `ConsultationDetail` but simply
  not referenced anywhere in this file, satisfying REQ-SHARE-003 by construction rather than by a
  filter step that could be gotten wrong).
- `ConsultationPage.vue`: a `shareUrl = computed(() => \`${location.origin}/share/consultations/${state.consultation.id}\`)`
  (guarded by `state.status === 'loaded'`), a "Copy Share Link" button calling
  `navigator.clipboard.writeText(shareUrl.value)` with a small success/error `ref`, and a
  `router-link :to="`/share/consultations/${id}`" target="_blank"` for "View Public Share Page" —
  both wrapped `print:hidden`.

## Architecture decisions

- **A brand-new page component, not `ConsultationPage` with a `readOnly` prop.** A parameterized
  single component would need conditional rendering around every mutating control and every
  cross-consultation field — the exact things this feature must never leak. A separate file makes
  "what does the share page render" auditable by reading one linear template, not by tracing every
  `v-if="!readOnly"` branch through a much larger file.
- **No new backend endpoint.** `GET /api/consultations/{id}` already returns everything
  unauthenticated; SPEC-029's containment is a frontend rendering decision (REQ-SHARE-009 makes
  this explicit), not a new access-control boundary the backend enforces.
- **`meta.public` on the route, read by `App.vue`, rather than a separate root layout/App
  component.** The router already carries per-route metadata; branching the nav on it is the
  smallest change that achieves "no nav links on public routes" without restructuring how
  `App.vue`/`router-view` compose.

## Affected areas

- `apps/web/src/router/index.ts`
- `apps/web/src/App.vue`
- `apps/web/src/App.spec.ts` (new, if none exists — nav-hiding behavior needs a test)
- `apps/web/src/pages/consultations/SharedConsultationPage.vue` (new)
- `apps/web/src/pages/consultations/SharedConsultationPage.spec.ts` (new)
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
