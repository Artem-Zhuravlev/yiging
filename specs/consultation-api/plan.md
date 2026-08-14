# Plan — Consultation API (SPEC-006)

**Depends on spec status:** `approved`

## Technical approach

`App\Readings\ConsultationController` (co-located with `Consultation`/`SqliteConsultationRepository`
per `docs/coding-rules.md`'s "application logic that needs persistence/HTTP context" rule),
constructed with only `Config` — matching `Kernel::invoke()`'s existing contract
(`new $handler[0]($this->config)`), so no change to `Kernel` is needed:

```php
final class ConsultationController
{
    private readonly ConsultationRepository $repository;
    private readonly ConsultationIdGenerator $idGenerator;
    private readonly Clock $clock;

    public function __construct(Config $config)
    {
        $this->repository = new SqliteConsultationRepository(Database::connect($config));
        $this->idGenerator = new UuidV4ConsultationIdGenerator();
        $this->clock = new SystemClock();
    }

    public function create(Request $request, array $vars): Response { ... }
    public function index(Request $request, array $vars): Response { ... }
    public function show(Request $request, array $vars): Response { ... }
}
```

- `create()`: decode JSON body, resolve `CastingMethodName::tryFrom($body['method'] ?? '')`
  (422 if `null`), build the matching `DivinationMethod` (`ThreeCoinsMethod`/`RandomMethod` with
  `new RandomIntCoinTosser()`, or `ManualMethod` with lines parsed from `$body['lines']`), call
  `cast()`, then `Consultation::create()` + `repository->save()`. Catches
  `\InvalidArgumentException` (from `Consultation::create()`, `ManualMethod`, or line-parsing)
  and maps to `422`.
- `index()`: `repository->findAll()`, map each to the JSON shape, `200`.
- `show()`: `repository->findById($vars['id'])`, `404` if `null`, else `200`.
- A private `ConsultationController::toJson(Consultation): array` maps the aggregate to the
  REQ-CAPI-006 shape — the only "DTO" this spec needs; no separate Request/Response class
  hierarchy, matching this project's existing flat-controller style (`HealthController` returns
  a plain array too).
- Manual-method line parsing (`{polarity, changing}` -> `Yijing\Core\Line`) is a small private
  helper on the controller, not a new reusable type — it exists nowhere else and inventing a
  shared "LineDto" for one call site would be premature.

## Architecture decisions

- **No Kernel changes.** Every controller in this codebase is constructed with `Config` alone;
  `ConsultationController` builds its own repository/generator/clock from `Config` in its
  constructor, same pattern `HealthController` already uses for `Config` itself. Changing
  `Kernel::invoke()` to support DI would touch SPEC-001's verified architecture for no gain here.
- **One JSON shape for list and single-resource views.** `GET /api/consultations` and
  `GET /api/consultations/{id}` return the same shape (full notes/tags included) — the
  dataset is small (a personal journal, not a multi-tenant feed), so a separate slim "summary"
  DTO would be optimizing for a scale problem that doesn't exist yet (violates "don't add
  abstractions for requirements that don't exist yet").
- **Domain exceptions become 422, not custom HTTP exception types.** `Consultation::create()`
  and `ManualMethod` already throw `\InvalidArgumentException` for exactly the conditions that
  should be `422`s; catching that one exception type in the controller is simpler than adding a
  parallel HTTP-specific exception hierarchy for validation.

## Affected areas

- `apps/api/src/Readings/ConsultationController.php`
- `apps/api/config/routes.php` (add 3 routes)
- `apps/api/tests/Readings/ConsultationControllerTest.php`

## Data / schema changes

None — reuses SPEC-005's schema as-is.

## Risks / open questions

- None currently open. `PATCH /api/consultations/{id}` (notes/tags after creation) is
  explicitly deferred (see spec.md "Out of scope") since `Consultation` already supports the
  domain operation; adding the route later is a small, isolated follow-up.
