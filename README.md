# Yijing

A digital I Ching (Book of Changes) study & practice platform — built on an actual model of the
64 hexagrams, 8 trigrams, yin/yang lines, and changing-line mechanics, not a generic "AI fortune
teller."

## Architecture

```
Browser → static Vue assets → PHP HTTP API → SQLite
```

No Docker, VM, Kubernetes, Redis, PostgreSQL, or Node.js runtime in production — see
[SPEC-001](specs/project-architecture/spec.md) for the full constraint list and rationale.

```
yijing/
├── apps/
│   ├── web/            Vue 3 + TypeScript + Vite + Router + Pinia + Tailwind
│   └── api/             PHP 8.2 + Composer + FastRoute + PDO/SQLite
├── packages/
│   ├── yijing-core/    Framework-free I Ching domain model
│   └── shared/          Reserved for cross-cutting shared types (currently empty)
├── specs/                Spec-driven development — see specs/README.md
├── docs/                 Coding rules, deployment guide
└── scripts/              migrate.php, seed.php, verify.mjs
```

## Spec-driven development

**This project does not accept code without a spec.** Read [`specs/README.md`](specs/README.md)
before implementing anything. In short:

```
NO SPEC = NO IMPLEMENTATION = NO COMMIT = NO PUSH
```

## Requirements

- Node.js 20+ and npm (frontend build/tooling only — not needed in production)
- PHP 8.2+ with `pdo_sqlite`, `mbstring`, `json`, `openssl` extensions
- Composer

## Running locally

```bash
# Frontend
npm install
npm run dev              # http://localhost:5173

# Backend (separate terminal)
cd apps/api
composer install
cp .env.example .env
php ../../scripts/migrate.php
composer serve            # http://127.0.0.1:8000 — try /api/health
```

## Verification

```bash
npm run verify
```

Runs, in order: web lint → web typecheck → web test → web build → api lint (php-cs-fixer) →
api static analysis (PHPStan level 8) → api test (PHPUnit) → yijing-core test (PHPUnit). Fails
fast on the first broken step. This same command runs automatically on `git push` via a Husky
pre-push hook (`.husky/pre-push`) — a failing push means a failing check, not a blocked tool.

## Deployment

See [`docs/deployment.md`](docs/deployment.md) — shared hosting, Apache, Nginx+PHP-FPM, and
plain-VPS instructions, all Docker-free.

## Current specs

| ID       | Feature               | Status     |
| -------- | ----------------------- | ---------- |
| SPEC-001 | Project Architecture   | `verified` |
| SPEC-002 | [I Ching Domain Model](specs/domain-model/spec.md) | `verified` |
| SPEC-004 | [Casting Engine](specs/casting-engine/spec.md) | `verified` |
| SPEC-005 | [Readings](specs/readings/spec.md) | `verified` |
| SPEC-006 | [Consultation API](specs/consultation-api/spec.md) | `verified` |
| SPEC-003 | [Hexagram Explorer](specs/hexagram-explorer/spec.md) | `verified` |
| SPEC-007 | [Hexagram Explorer UI](specs/hexagram-explorer-ui/spec.md) | `verified` |
| SPEC-009 | [Consultation Flow UI](specs/consultation-flow-ui/spec.md) | `verified` |
| SPEC-008 | [AI Interpretation](specs/ai-interpretation/spec.md) | `verified` |
| SPEC-010 | [Interpretation UI](specs/interpretation-ui/spec.md) | `verified` |
| SPEC-011 | [Gemini Interpretation Provider](specs/gemini-interpretation-provider/spec.md) | `verified` (code); live call unverified |
| SPEC-012 | [AI Endpoint Rate Limiting](specs/ai-rate-limiting/spec.md) | `verified` |
| SPEC-013 | [Consultation Notes & Tags Editing](specs/consultation-editing/spec.md) | `verified` |
| SPEC-014 | [Complete Hexagram Relationships](specs/hexagram-relationships/spec.md) | `verified` |
| SPEC-015 | [Hexagram Relationship Navigation](specs/hexagram-relationship-nav/spec.md) | `verified` |
| SPEC-016 | [Visual Hexagram Editor](specs/hexagram-editor/spec.md) | `verified` |
| SPEC-017 | [Hexagram Comparison](specs/hexagram-comparison/spec.md) | `verified` |
| SPEC-018 | [Deep Hexagram Page](specs/deep-hexagram-page/spec.md) | `verified` |
| SPEC-019 | [Rich Consultation Context](specs/consultation-context/spec.md) | `verified` |
| SPEC-020 | [Consultation Outcome](specs/consultation-outcome/spec.md) | `verified` |
| SPEC-021 | [Follow-up Consultations](specs/consultation-follow-ups/spec.md) | `verified` |
| SPEC-022 | [Consultation Timeline](specs/consultation-timeline/spec.md) | `verified` |
| SPEC-023 | [Repeated Pattern Detection](specs/repeated-pattern-detection/spec.md) | `verified` |
| SPEC-024 | [Personal Statistics](specs/personal-statistics/spec.md) | `verified` |
| SPEC-025 | [Consultation Favorites](specs/consultation-favorites/spec.md) | `verified` |
| SPEC-026 | [Full-Text Search](specs/full-text-search/spec.md) | `verified` |
| SPEC-027 | [Consultation Print / PDF Export](specs/consultation-print-export/spec.md) | `verified` |
| SPEC-028 | [Consultation History Backup](specs/history-backup/spec.md) | `verified` |
| SPEC-029 | [Consultation Public Share Link](specs/consultation-public-share/spec.md) | `verified` |
| SPEC-030 | [Practice Journal](specs/practice-journal/spec.md) | `verified` |
| SPEC-031 | [Hexagram Favorites](specs/hexagram-favorites/spec.md) | `verified` |

`packages/yijing-core` now implements `Line`, `Trigram` (8), `Hexagram` (64, King Wen sequence,
plus `fromKingWenNumber()`), `changeLine()`/`getResultingHexagram()`, `YijingRelations`
(nuclear/opposite/complement), and — as of the classical-text pass — full judgment/image/6
line-statement text for all 64 hexagrams (James Legge, 1899, public domain; transcribed and
cross-checked against an independent source) via `Data/HexagramTextCatalog.php`. These fields
are non-nullable now; every hexagram everywhere (Explorer, consultation detail) shows real
classical text instead of a placeholder. 51 tests, 1530 assertions — see
[SPEC-002](specs/domain-model/spec.md).

`apps/api/src/Casting` implements the casting engine — `DivinationMethod` interface with
`ThreeCoinsMethod` (traditional 6/7/8/9 coin-sum rule), `ManualMethod`, and `RandomMethod`
(dev/test only), all built on an injected `CoinTosser` so casting stays testable without true
randomness — see [SPEC-004](specs/casting-engine/spec.md).

`apps/api/src/Readings` implements the `Consultation` aggregate (question, method used,
primary/resulting hexagram, notes with before/after/later labels, tags) and its SQLite
persistence (`ConsultationRepository`/`SqliteConsultationRepository`, migration for
`consultations`/`consultation_notes`/`tags`/`consultation_tags`) — see
[SPEC-005](specs/readings/spec.md).

`POST /api/consultations`, `GET /api/consultations`, and `GET /api/consultations/{id}` are now
live — `ConsultationController` wires a chosen `Casting` method into a persisted `Consultation`
and returns it as JSON, with `422`/`404` handled explicitly (no uncaught exceptions reaching
the client) — see [SPEC-006](specs/consultation-api/spec.md).

`GET /api/hexagrams`, `GET /api/hexagrams/{number}`, and `GET /api/trigrams` are now live —
read-only browsing over `yijing-core`'s static reference data, no database involved — see
[SPEC-003](specs/hexagram-explorer/spec.md). `apps/api` is at 52 tests, 380 assertions.

`apps/web` now has a real page: `/hexagrams` and `/hexagrams/:number`, backed by a small typed
API client (`shared/api`) and a Vite dev proxy so `npm run dev` talks to the PHP dev server
same-origin, no CORS needed — see [SPEC-007](specs/hexagram-explorer-ui/spec.md). This is the
pattern (`entities/<domain>` for types+fetch, `pages/<domain>` for routes, feature-sliced
layering) later pages (the consultation flow) will follow.

The consultation flow is live: `/consultations/new` (question + Three Coins/Manual casting),
`/consultations/:id` (full detail — hexagram diagrams with changing lines marked, notes, tags),
and `/consultations` (history, newest-first) — see
[SPEC-009](specs/consultation-flow-ui/spec.md). `apps/web` is now at 30 tests. This completes
the plan's core MVP loop end to end: ask a question → cast → see the hexagram → find it again
later.

`POST /api/interpretations/{consultationId}` is now live — builds an `InterpretationContext`
from a `Consultation` (its real primary/resulting hexagrams, only the *changing* lines' text,
existing notes) and hands it to a swappable `InterpretationProvider`. The only implementation
so far is `MockInterpretationProvider`: fully deterministic, built entirely from the context's
own canonical text, no API key or external call — every `sourceReferences` entry traces back to
real Legge text, nothing invented — see [SPEC-008](specs/ai-interpretation/spec.md). `apps/api`
is now at 60 tests, 413 assertions.

`/consultations/:id` now has a "Get Interpretation" button rendering all 8 `Interpretation`
fields in a clearly separate, bordered section — never interleaved with the consultation's own
canonical hexagram/text data, matching the plan's explicit requirement to keep AI output and
canonical source visually distinct — see [SPEC-010](specs/interpretation-ui/spec.md). `apps/web`
is now at 35 tests.

**Every spec is now `verified`, SPEC-001 through SPEC-010.** The entire backend, domain model,
and frontend loop from the original plan's MVP definition (ask → cast → see → interpret → find
again) is complete and working end to end against the real running stack, not just unit-tested.

The full production path (Phase 10 of the original plan) has been dry-run end to end in an
isolated copy: `composer install --no-dev --optimize-autoloader` pulls in zero dev tooling,
`php scripts/migrate.php`/`seed.php` bootstrap a brand-new SQLite database from nothing
(including migrations added well after SPEC-001 was first verified), and the app serves the
full request path correctly under `APP_ENV=production` and with no `.env` file at all — see
[SPEC-001](specs/project-architecture/spec.md)'s 2026-08-14 re-verification note.

A pass against the plan's own security checklist (section 31) turned up one real gap — no
maximum length on user-supplied text — since fixed: `Consultation`'s `question` is capped at
2000 characters and `ConsultationNote.text` at 5000, both counted by character (not byte, so
non-Latin text like Chinese or Cyrillic isn't penalized), enforced server-side with a matching
client-side `maxlength` hint. Everything else on that checklist (prepared statements
throughout, no `v-html` anywhere so Vue's default output-escaping holds, no secrets in the
frontend) was already satisfied. See [SPEC-005](specs/readings/spec.md)'s 2026-08-14 addendum.

A real `InterpretationProvider` now exists: `GeminiInterpretationProvider`, backed by Google's
Gemini API, selectable via `AI_PROVIDER=gemini` in `apps/api/.env` (default stays `mock` — no
key needed, safe out of the box). `sourceReferences` is never LLM-generated for either
provider — `InterpretationContext::defaultSourceReferences()` computes it once, shared, so a
citation can never be hallucinated regardless of which provider answered. A misconfigured
provider (`gemini` selected, empty key) fails loudly at startup rather than silently serving
mock output; any Gemini API failure maps to a clean `502`, never a raw stack trace — and now
neither does *any* uncaught exception anywhere in the app, since `Kernel::handle()` gained a
catch-all specifically because this is the app's first dependency on something genuinely
outside its control. See [SPEC-011](specs/gemini-interpretation-provider/spec.md) — **and
note its one open item: this session had no API key to test against, so the exact request
contract is verified by research (Google's current docs, cross-checked across 3 fetches), not
by a real call. Set `AI_API_KEY` in `apps/api/.env` and try it to complete that verification.**

`POST /api/interpretations/{id}` is now rate-limited — `AI_RATE_LIMIT_MAX` (default 20)
requests per `AI_RATE_LIMIT_WINDOW_SECONDS` (default 3600) per client IP, backed by a new
`rate_limit_hits` SQLite table, no external cache needed. Applies identically regardless of
which provider is configured; a rejected request never reaches the repository, context
builder, or provider at all. Manually verified against the live dev server: 20 real requests
all `200`, the 21st `429` with `Retry-After: 3600` — see
[SPEC-012](specs/ai-rate-limiting/spec.md). Known limitation, stated plainly: behind a reverse
proxy, this needs that deployment's trusted-proxy configuration to key on the real client IP
rather than the proxy's own address (still caps *total* volume through the proxy either way).

`PATCH /api/consultations/{id}` is now live — the plan's Definition of Done listed "add a note"
as an explicit MVP step, and until now nothing between the domain layer
(`Consultation::withAddedNote()`/`withAddedTag()`, present since SPEC-005) and the read-only
display on `ConsultationPage` (since SPEC-009) actually let a person add one. `/consultations/:id`
now has working "add a note" and "add a tag" forms, each with its own loading/error state, that
update the page in place on success without a full reload — see
[SPEC-013](specs/consultation-editing/spec.md). This completes the plan's MVP loop end to end:
ask → cast → see → interpret → **add a note** → find again.

`GET /api/hexagrams/{id}` and `GET /api/hexagrams` now include a `relationships` object —
`nuclear` (互卦), `reversed` (綜卦, line order flipped), and `complement` (錯卦, every line's
polarity flipped) — each the existing `{kingWenNumber, chineseName, pinyin}` summary shape,
computed entirely by `packages/yijing-core`'s already-tested `YijingRelations` (no new domain
logic, no relationship math in the frontend). This is feature 21 of the plan's next batch
(features 21-40); the Hexagram Explorer UI that navigates this relationship graph is feature 22,
next. See [SPEC-014](specs/hexagram-relationships/spec.md).

`HexagramDetailPage` now has a "Related Hexagrams" section — nuclear/reversed/complement each
rendered as a `router-link` (self-referential relationships, e.g. hexagram 1's own nuclear, render
as plain text instead of a dead link to the current page), so the relationship graph SPEC-014
exposed is now actually clickable end to end: this is feature 22 of the plan's next batch.
Verifying this manually surfaced a real pre-existing bug from SPEC-007 — `HexagramDetailPage`
fetched its hexagram in `onMounted()`, which only runs once, so navigating between two hexagram
pages via a same-route param change (exactly what a relationship link does) silently kept
showing the *previous* hexagram under the new URL. Fixed by watching the route param instead
or `onMounted()`. See [SPEC-015](specs/hexagram-relationship-nav/spec.md).

`GET /api/hexagrams/from-lines?lines=yang,yin,...` is now live — a read-only endpoint computing a
hexagram from six caller-supplied line polarities via the existing `Hexagram::fromLines()` (no
persistence, no new domain logic), returning the exact same shape `GET /api/hexagrams/{id}`
already does. `/hexagrams/editor` (linked from the Hexagram Explorer) drives it with six yin/yang
toggles: flipping any line re-fetches and updates the number, name, trigrams, and relationships
live — zero hexagram-computation logic in `apps/web`. See
[SPEC-016](specs/hexagram-editor/spec.md).

`GET /api/hexagrams/compare?a={n}&b={n}` is now live — returns both hexagrams' full detail plus a
6-position line-by-line diff (`packages/yijing-core`'s new `HexagramComparator::compareLines()`,
the one genuinely new calculation this feature needed) and upper/lower trigram-difference flags.
"Structural relationships" and "relevant texts" needed no new API surface at all — both were
already present in each hexagram's own `relationships`/`judgment`/`image` fields (SPEC-002/014),
so `/hexagrams/compare` derives its "54 is 11's nuclear hexagram"-style note with a plain equality
check, the same pattern `HexagramDetailPage` already established (SPEC-015). `ConsultationPage`
links straight into it with its primary/resulting King Wen numbers, satisfying the "should also
work for a consultation's pair" requirement via composition rather than a separate code path. See
[SPEC-017](specs/hexagram-comparison/spec.md).

`/hexagrams/{n}` is now the actually-deep page feature 25 asked for: it shows the hexagram's own
Unicode glyph (`Hexagram::symbol()`, new in `packages/yijing-core` — `mb_chr(0x4DC0 +
kingWenNumber - 1)`, verified against the Unicode standard's own King-Wen-ordered character names
for hexagrams 1/11/44/54/64) and all six line texts (`lineStatements`, populated since SPEC-002
but never rendered until now), plus a source-attribution line making the existing "James Legge,
1899, public domain" fact visible in the UI itself, not just in this README. See
[SPEC-018](specs/deep-hexagram-page/spec.md).

**Feature 26 (Translation Comparison) is blocked, not skipped:** it needs a second real,
independently-sourced, public-domain translation to compare against Legge's — the same kind of
dedicated sourcing/parsing/cross-checking pass SPEC-002 did for Legge himself. Inventing or
LLM-generating a second "translation" would violate this project's own rule against fabricated
canonical data (plan feature 27 says so explicitly, and the practice has held throughout). This
needs a real source identified before it can be built.

Feature 30 of the plan's next batch is live: `Consultation` now carries five optional,
independently-settable free-text fields (`context`, `whatHappenedBefore`,
`whatUserWantsToUnderstand`, `backgroundInformation`, `initialInterpretation`), settable at
creation time or edited afterward via the existing `PATCH /api/consultations/{id}` endpoint
(SPEC-013), which now distinguishes "field absent" (leave unchanged) from "field explicitly
`null`" (clear it) for these five keys — the first time this API needed that distinction.
`NewConsultationPage` collapses the five optional inputs behind a "Add more context" disclosure
so the core question-first flow stays uncluttered; `ConsultationPage` shows and edits them via a
pre-filled form. Verified against a genuinely pre-migration consultation (created earlier in this
same session, before this schema change existed) loading correctly with all five fields `null` —
real backward-compatibility proof, not just a synthetic test. See
[SPEC-019](specs/consultation-context/spec.md).

Feature 31 of the plan's next batch is live: a consultation can now carry a
**`ConsultationOutcome`** — `whatActuallyHappened`, `outcome`, `reflection`, plus a `recordedAt`
timestamp — stored in its own `consultation_outcomes` table (one row per consultation, linked by
`consultation_id` as its primary key) rather than more columns on `consultations`, exactly the
"separate historical record" the feature asked for. Settable/editable via the same
`PATCH /api/consultations/{id}` endpoint SPEC-013/019 already extended, reusing SPEC-019's
present-string-sets/present-null-clears/absent-leaves-unchanged semantics for the three new keys.
Implementing this deliberately audited every existing `Consultation` wither method
(`withAddedNote`/`withAddedTag`/`withUpdatedContext`) to thread the new `outcome` field through
explicitly — the exact bug class SPEC-019 found by accident, this time checked for on purpose.
Verified live end to end, including recording a real outcome on a consultation created earlier in
this session (well before this feature existed) without disturbing its question, hexagrams, notes,
tags, or context fields. See [SPEC-020](specs/consultation-outcome/spec.md).

Feature 32 of the plan's next batch is live: consultations can now be explicitly linked —
`followUpToConsultationId` is a plain self-referential foreign key on `consultations`, resolved
server-side into readable `{id, question}` summaries in both directions (`followUpTo` the
consultation this one follows up on; `followUps` every consultation that follows up on this one)
so the UI never has to join raw IDs itself. Set via the same `PATCH`/`POST` endpoints, reusing
SPEC-019's set/clear/leave-unchanged semantics. `/consultations/new?followUpTo={id}` is the
primary flow — a "Create Follow-up" link on `ConsultationPage` carries the target forward, shown
back to the user as "Follow-up to: {question}" before they even submit. Verified live end to end
on the same consultation used throughout this session's manual checks: created a real follow-up
from it, confirmed both directions navigate correctly, and confirmed it — genuinely pre-dating
this migration by several specs — still loaded correctly throughout. See
[SPEC-021](specs/consultation-follow-ups/spec.md).

`ConsultationHistoryPage` now groups consultations under a date heading per local calendar day
(newest-first, both across and within groups) and adds a client-side, multi-select tag filter
(AND semantics — a consultation must carry every selected tag) built entirely from data
`GET /api/consultations` already returns: no new endpoint, query parameter, or schema change.
Date groups with zero matches after filtering don't render their heading, and a distinct "No
consultations match the selected tags" message is shown separately from the existing "no
consultations yet" empty state. Manually verified against the real running dev server: created
two new tagged consultations, confirmed date grouping against the existing multi-day history,
confirmed AND-filtering narrows correctly, and confirmed the zero-match message renders when two
non-overlapping tags are both selected. See [SPEC-022](specs/consultation-timeline/spec.md).

`GET /api/consultations/{id}` (the single-consultation endpoint only — deliberately not the list,
create, or update responses, to keep the O(n²)-shaped changing-lines comparison off any code path
that runs per-row across the whole history) now returns a `repeats` object: every other
consultation sharing this one's primary hexagram, resulting hexagram, or exact changing-line set,
each as its own newest-first list of `{id, question}` links. Matching reuses
`changing_line_positions`' existing canonical (always ascending-by-position) JSON encoding for a
plain SQL string-equality check — no per-row PHP decode/compare needed. `ConsultationPage` renders
up to three distinctly labeled sections ("Same primary hexagram before" / "Same resulting hexagram
before" / "Same changing lines before"), each only when non-empty, nothing at all when a
consultation has no repeats. Manually verified against the real running dev server: cast two
consultations with an identical hexagram/changing-line pattern via the manual method, confirmed
all three sections linked correctly between them. See
[SPEC-023](specs/repeated-pattern-detection/spec.md).

`GET /api/statistics` (new) aggregates across the entire consultation history — total count,
per-hexagram cast frequency, an aggregate yin/yang line ratio, and per-tag frequency — computed
via SQL `GROUP BY` for the two purely-relational aggregates, with only the yin/yang tally (King
Wen line-polarity is `yijing-core` domain logic, not a stored column) looping in PHP over a single
narrow column, never a full `Consultation` hydration. Both hexagram and yin/yang aggregates use
each consultation's primary (as-cast) hexagram consistently, not the resulting one. `/statistics`
(linked from the main nav) renders all four; a zero-consultation history shows a distinct empty
message rather than empty lists. Manually verified against the real running dev server's genuine
seeded history (10 consultations): confirmed `yin + yang = totalConsultations * 6` (24 + 36 = 60)
and that every section rendered correctly. See [SPEC-024](specs/personal-statistics/spec.md).

Consultations can now be marked favorite — `consultations` gained a plain `is_favorite` column,
toggleable via the existing `PATCH /api/consultations/{id}` (a genuine boolean, no null-clear
semantics the way SPEC-019's optional text fields have) and threaded through every existing wither
per the by-now-standard checklist. `ConsultationPage` has a "☆ Add to Favorites" / "★ Favorited"
toggle button; `ConsultationHistoryPage` gained a "★ Favorites only" toggle that composes with
SPEC-022's tag filter (AND) over the already-fetched list, no new query parameter. Hexagram
favorites are deliberately deferred — `HexagramController` has zero database access by design
(SPEC-003), so adding favorites there is a materially separate change, not silently folded in
here. Manually verified against the real running dev server: favorited a consultation, confirmed
the history page's toggle narrowed correctly and combined with an active tag filter. See
[SPEC-025](specs/consultation-favorites/spec.md).

`ConsultationHistoryPage` and `HexagramListPage` both gained a search box — plain case-insensitive
substring matching over data each page already has fully loaded (`question`/`notes[].text` for
consultations, `chineseName`/`pinyin`/`judgment`/`image` for hexagrams), no new API surface,
composing as an additional AND stage with the existing tag/favorites filters on the history page.
Manually verified against the real running dev server: searched "heaven" on `/hexagrams` (narrowed
64 hexagrams down to the 10 whose Judgment/Image text actually contains it) and "repeat" on
`/consultations` (narrowed to exactly the two consultations created earlier this session with
"Repeat check" in their questions). See [SPEC-026](specs/full-text-search/spec.md).

`ConsultationPage` now has a "Print / Export" button (`window.print()`) with a print stylesheet
(Tailwind's `print:` variant) hiding nav, back-link, favorite toggle, "Compare hexagrams"/"Create
Follow-up" links, the note/tag-adding forms, and the Save Context/Save Outcome/Get Interpretation
buttons — deliberately no server-side PDF library, since the browser's own Save-as-PDF already
does this for free and a new PHP PDF dependency would cut against SPEC-001's minimal-runtime-
dependency posture. The AI Interpretation section itself is print-hidden unless an interpretation
has actually been fetched, avoiding an empty dashed box in the printout. Manually verified: read
the generated stylesheet directly (`@media print { .print\:hidden { display: none; } }`) confirms
Tailwind emitted the rule correctly; the button and all thirteen hidden elements pass their own
component tests. See [SPEC-027](specs/consultation-print-export/spec.md).

`ConsultationHistoryPage` now has "Export Backup (JSON)" (a pure client-side download of the
already-loaded history, no extra request) and "Import Backup (JSON)" (a new
`POST /api/consultations/import`, all-or-nothing in one transaction, rejecting the whole batch if
any id already exists or any follow-up link is unresolvable). Import reconstructs every field via
`Consultation::reconstitute()` — the same "trusted, previously-validated state" factory the
repository's own row-hydration already uses — preserving original ids, timestamps, hexagrams,
notes' own timestamps, tags, context, outcome, favorite flag, and follow-up links exactly, via a
two-pass insert (rows first, links second) so cross-references resolve regardless of array order.
Manually verified against the real running dev server: re-importing the live history was correctly
rejected (duplicate ids), and both the empty-array and malformed-JSON edge cases behaved exactly
as the automated `ConsultationControllerTest::testImportRoundTripsAFullExportedConsultation` (a
full export→fresh-database→import→field-by-field-comparison test) already proves for the success
path. See [SPEC-028](specs/history-backup/spec.md).

`/share/consultations/{id}` (new) renders a deliberately minimal, brand-new page component — not
a parameterized `ConsultationPage` — showing only question, hexagrams, changing lines, notes,
tags, context, and outcome, read-only, reusing `GET /api/consultations/{id}` exactly as-is (no new
endpoint, no new access control: this app has no authentication anywhere, so the containment this
feature adds is purely presentational, stated explicitly in the spec rather than left implied).
Critically, it never reads `followUpTo`/`followUps`/`repeats` from the fetched response, since
each of those fields carries another consultation's own `{id, question}` — omitted by construction
rather than filtered, so there's no filter logic to get wrong. `App.vue`'s main nav collapses to
just the site name (no links) on any route tagged `meta.public`, so a share-link recipient has no
path into the rest of the history. `ConsultationPage` gained "Copy Share Link"
(`navigator.clipboard`) and "View Public Share Page" controls. Manually verified against the real
running dev server: the share page for a consultation with real follow-up and repeated-pattern
data showed none of it, and the nav rendered as bare "Yijing" text with zero links.
See [SPEC-029](specs/consultation-public-share/spec.md).

`App\Journal` is now a real module — the first thing in the `apps/api/src/Journal` directory that
had sat empty since the project's early scaffolding. `journal_entries` (`id`, `text`,
`created_at`, no foreign key to anything) holds free-form entries independent of any consultation;
`POST`/`GET /api/journal` follow the same validate-then-persist shape every other write endpoint
in this app already uses. `/journal` (new nav link) adds an entry via a form and lists existing
ones date-grouped, reusing `ConsultationHistoryPage`'s exact grouping logic so journal and history
read consistently. Authentication (feature 9) was intentionally skipped this session at the user's
explicit direction — it's a materially different kind of change (protects every existing endpoint,
interacts with SPEC-029's just-shipped public share route) warranting its own dedicated pass
rather than being folded into this batch. Manually verified against the real running dev server:
added a real entry via the form, confirmed it appeared immediately under today's date heading.
See [SPEC-030](specs/practice-journal/spec.md).

Feature 4 is now fully closed: `HexagramController` gained its first-ever database access (a new
`favorite_hexagrams` marker table, `king_wen_number INTEGER PRIMARY KEY`, no relation to
`consultations`) — the constructor comment explaining it previously had none was removed, since
that premise no longer holds. `PUT`/`DELETE /api/hexagrams/{number}/favorite` toggle a favorite
(both idempotent, `404` for an invalid number); `GET /api/hexagrams`/`{id}` gained `favorite`,
`index()` computing it from one bulk `allFavoriteNumbers()` query rather than 64 individual
lookups. `HexagramListPage` gained a star per card plus a "Favorites only" filter composing with
SPEC-026's search; `HexagramDetailPage` gained a toggle button matching `ConsultationPage`'s own
pattern. `shared/api/http.ts` gained `apiPut`/`apiDelete` (both `Promise<void>`, since every
caller responds `204 No Content` with nothing to parse). Manually verified against the real
running dev server: starred hexagram 1 from the Explorer grid without triggering card navigation,
confirmed the favorites-only filter narrowed correctly, toggled hexagram 2's favorite from its
detail page, and confirmed `404`/idempotent-`204` directly against the API for an out-of-range
number and a repeated unmark. See [SPEC-031](specs/hexagram-favorites/spec.md).

Next recommended steps: AI interpretation layering, profiles, source-grounding, and conversation
(building on the existing `InterpretationContext`/`InterpretationProvider` abstraction),
authentication (feature 9, deliberately skipped this session — see above), or provide a second
public-domain I Ching translation source to unblock features 26/27, or verify the live Gemini call
(see above) whenever an `AI_API_KEY` is available.
