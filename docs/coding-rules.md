# Coding Rules

## Spec-driven development

See [`specs/README.md`](../specs/README.md). This is the master rule — everything below is
secondary to it.

## TypeScript (`apps/web`, `packages/shared`)

- Strict mode is on (`@vue/tsconfig/tsconfig.dom.json`, `noUnusedLocals`, `noUnusedParameters`).
  Do not weaken it.
- No `any`. If a type is genuinely unknown, use `unknown` and narrow it.
- No business/domain logic inside `.vue` components — components render state and dispatch
  events. Domain logic belongs in `entities/` or `features/`, not in a component's `<script>`.
- Feature-oriented structure: `pages/ → widgets/ → features/ → entities/ → shared/`. A layer may
  only import from itself or a layer to its right (e.g. `features` may use `entities`/`shared`,
  never the reverse).
- Format with Prettier (`npm run format` in `apps/web`), lint with ESLint
  (`npm run lint`). Both must pass before commit.

## PHP (`apps/api`, `packages/yijing-core`)

- `declare(strict_types=1);` in every file.
- PSR-12, enforced by php-cs-fixer (`composer lint` / `composer lint:fix` in `apps/api`).
- PHPStan level 8 (`composer stan` in `apps/api`). Do not lower the level or add broad
  `@phpstan-ignore` annotations to silence real issues — fix the type instead.
- No business/domain logic inside controllers — controllers translate HTTP ↔ domain calls,
  nothing more. Domain logic belongs in `packages/yijing-core` (pure I Ching model) or
  `apps/api/src/<Domain>/` (application logic that needs persistence/HTTP context).
- `packages/yijing-core` has zero framework/HTTP/database/filesystem/AI dependencies — see
  SPEC-001 (REQ-ARCH-005) and SPEC-002 (REQ-DM-001). If code in that package needs any of
  those, it belongs in `apps/api` instead.
- AI must never be a source of truth for domain mechanics (hexagram numbers, line structure,
  changing-line rules, casting algorithm, classical text) — see SPEC-008. AI consumes
  structured domain data; it does not invent or override it.

## General

- No magic numbers — name constants, especially anything encoding I Ching structure (e.g. "6"
  for line count, "64" for hexagram count) so intent is clear at the call site.
- No duplicated domain logic between `apps/api` and `apps/web` — if both need the same rule,
  it belongs in `packages/yijing-core` (PHP) with the frontend calling the API rather than
  reimplementing the rule in TypeScript.
- No global mutable state.
- Don't add abstractions, config flags, or generalization for requirements that don't exist yet.
