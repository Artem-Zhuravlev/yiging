# Plan — Hexagram Explorer UI (SPEC-007)

**Depends on spec status:** `approved`

## Technical approach

Feature-sliced layout, following `docs/coding-rules.md`'s existing layering rule
(`pages → widgets → features → entities → shared`):

```
apps/web/src/
├── shared/api/http.ts          apiGet<T>(), ApiError
├── entities/hexagram/
│   ├── model.ts                 Hexagram, HexagramLine, TrigramSummary types
│   ├── api.ts                   fetchHexagrams(), fetchHexagram(number)
│   └── ui/HexagramLines.vue     renders 6 lines bottom-to-top, yin/yang
├── pages/hexagrams/
│   ├── HexagramListPage.vue     /hexagrams
│   └── HexagramDetailPage.vue   /hexagrams/:number
├── App.vue                       + nav shell (Home / Hexagrams)
└── router/index.ts               + 2 routes
```

- `apiGet<T>(path)`: `fetch(base + path)`, throws `ApiError` (status + message from the API's
  own `{"error": "..."}` body when present) on non-2xx, otherwise resolves parsed JSON as `T`.
  No runtime schema validation (e.g. zod) — the API is same-repo, same-spec-driven, and adding
  a validation layer for a same-origin trusted API is speculative until there's a concrete
  reason (a public/third-party consumer) to distrust the response shape.
- Base URL: `import.meta.env.VITE_API_BASE_URL ?? '/api'`. No `.env` file required for local
  dev to work — the Vite proxy (below) makes the `/api` default reachable without any env
  file; `.env.example` documents the override for deployments that need one.
- Vite dev proxy (`vite.config.ts`): `server.proxy['/api'] -> http://127.0.0.1:8000` (matches
  `composer serve`'s documented port in the root README). Dev-only; production serves the built
  `dist/` from a real web server per `docs/deployment.md`, where `/api` is already routed
  server-side.
- `HexagramLines.vue` takes `lines: HexagramLine[]` as a prop, renders positions 6→1 top to
  bottom (reversing the position-1-first array for display only — the data itself is never
  reordered/mutated). Yang = one solid bar; yin = two bars with a gap between them.
- Both pages: `ref`/`onMounted` fetch (no Pinia store — see spec.md "Out of scope"), a
  discriminated loading/error/notFound/loaded local state so the template can render exactly
  one of 4 states without ambiguous flag combinations.

## Architecture decisions

- **No Pinia store for hexagram data.** Nothing yet needs this data outside the page that
  fetches it; `entities/hexagram/api.ts` is already the single place the fetch logic lives, so
  adding a store now would be state management with no consumer to justify it.
- **No CORS on the backend.** Solved via Vite's dev proxy instead — keeps `apps/api` unchanged
  (matches SPEC-003/006's scope boundaries) and matches the same-origin deployment
  `docs/deployment.md` already documents as the default. A cross-origin deployment is
  explicitly out of scope (see spec.md) and can add CORS headers as its own scoped change later.
- **No schema validation library.** See `apiGet<T>()` above — trusting the shape for a
  same-repo, spec-driven API. If the API and frontend ever drift, that's a bug the spec/test
  discipline on the API side should catch, not something the frontend needs to guard against
  at runtime.
- **`null` judgment/image/lineStatements render as an explicit placeholder string**, not blank
  — mirrors SPEC-002's own choice not to default nullable classical-text fields to empty
  strings; the UI shouldn't quietly hide that distinction either.

## Affected areas

- `apps/web/src/shared/api/http.ts` (+ test)
- `apps/web/src/entities/hexagram/model.ts`
- `apps/web/src/entities/hexagram/api.ts`
- `apps/web/src/entities/hexagram/ui/HexagramLines.vue`
- `apps/web/src/pages/hexagrams/HexagramListPage.vue` (+ test)
- `apps/web/src/pages/hexagrams/HexagramDetailPage.vue` (+ test)
- `apps/web/src/App.vue` (nav shell)
- `apps/web/src/router/index.ts` (2 new routes)
- `apps/web/vite.config.ts` (dev proxy)
- `apps/web/.env.example` (new)

## Data / schema changes

None.

## Risks / open questions

- None currently open. Cross-origin deployment (CORS) and a Pinia store are both explicitly
  deferred with a stated trigger condition (see spec.md "Out of scope"), not silently dropped.
