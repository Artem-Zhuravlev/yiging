# Plan — Interpretation Follow-Up Questions (SPEC-034)

**Depends on spec status:** `approved`

## Technical approach

- `apps/api/src/AI/ConversationExchange.php` (new, readonly): `question: string`, `answer:
  string`.
- `apps/api/src/AI/FollowUpAnswer.php` (new, readonly): `answer: string`, `sourceReferences:
  list<string>`.
- `InterpretationProvider` interface gains `answerFollowUp(InterpretationContext $context, array
  $history, string $question): FollowUpAnswer` (`$history` typed `list<ConversationExchange>`).
- `GeminiInterpretationProvider::answerFollowUp()`: builds a prompt (question, primary/resulting
  hexagram identity + judgment/image, changing lines, existing notes — the same context-grounding
  block `buildPrompt()` already assembles, factored into a shared private helper both
  `interpret()` and `answerFollowUp()` call) followed by the conversation history rendered as
  "Q: ... / A: ..." pairs in order, then the new question, then an instruction to answer grounded
  only in the given text. Requests `{"type": "object", "properties": {"answer": {"type":
  "string"}}, "required": ["answer"]}` via the same `GeminiClient::generateJson()` used by
  `interpret()`. Returns `FollowUpAnswer($data['answer'], $context->defaultSourceReferences())`;
  throws `InterpretationProviderException` if `answer` is missing/non-string/empty, mirroring
  `interpret()`'s own required-field validation.
- `MockInterpretationProvider::answerFollowUp()`: `FollowUpAnswer` with a fixed-format string
  ("This is a placeholder answer from the mock interpretation provider. Your question was:
  \"{$question}\" — it does not have real understanding of this conversation.") and
  `$context->defaultSourceReferences()`.
- `InterpretationController::followUp(Request, array $vars)`: same rate-limit-first ordering as
  `create()` (reuses the same `$this->rateLimiter`/`$rateLimitKey`), then validates `question`
  (non-empty, ≤2000 chars — `422` before repository lookup) and `history` (must be a list of
  objects each with string `question`/`answer`, or absent → `[]`), then `findById()` (`404` if
  missing), builds the context via the existing `contextBuilder`, calls
  `$this->provider->answerFollowUp($context, $history, $question)` inside the same try/catch
  `InterpretationProviderException` → `502` pattern `create()` already has.
- `config/routes.php` gains `POST /api/interpretations/{id}/followup`.
- `apps/web/src/entities/interpretation/model.ts` gains `ConversationExchange { question: string;
  answer: string }`, `FollowUpAnswer { answer: string; sourceReferences: string[] }`.
- `apps/web/src/entities/interpretation/api.ts` gains `requestFollowUp(consultationId, question,
  history: ConversationExchange[])`.
- `ConsultationPage.vue`: `conversations: Record<InterpretationLens, ConversationExchange[]>`
  (all four keys `[]`), a `followUpText` ref and `followUpFormState` (same `FormState` shape used
  elsewhere), a form rendered only when `interpretationState.status === 'loaded'`; submitting
  calls `requestFollowUp(id, followUpText.value, conversations.value[selectedLens.value])`,
  appends `{question, answer}` to `conversations.value[selectedLens.value]` on success.

## Architecture decisions

- **One `InterpretationProvider` interface, two capabilities, not a second provider
  abstraction.** A follow-up answer and an interpretation are both "ask the configured AI service
  something, grounded in this context" — splitting them into separate provider interfaces would
  duplicate the mock/gemini selection and config-validation logic `InterpretationController`
  already has for no real benefit.
- **The context-grounding prompt block is factored into a shared private helper.** Both
  `interpret()` and `answerFollowUp()` need "here is the question, hexagram, changing lines,
  notes" as a prefix — duplicating that block risks the two prompts drifting apart in what
  canonical text they ground on, which would undermine the "never invent beyond what's given"
  guarantee for whichever one wasn't kept in sync.
- **Follow-up shares the interpretation endpoint's rate limiter and key, not a separate
  budget.** Both are real provider calls with real cost; a separate follow-up-specific limit
  would let a user double their effective hourly budget by alternating between the two endpoints.
- **Conversation state lives in `ConsultationPage`'s local `ref`s, keyed by lens, never
  persisted.** Matches SPEC-033's exact per-lens caching pattern and SPEC-008's "AI output isn't
  persisted" stance — consistent with everything else this session has built on top of the AI
  module.

## Affected areas

- `apps/api/src/AI/ConversationExchange.php` (new)
- `apps/api/src/AI/FollowUpAnswer.php` (new)
- `apps/api/src/AI/InterpretationProvider.php`
- `apps/api/src/AI/GeminiInterpretationProvider.php`
- `apps/api/src/AI/MockInterpretationProvider.php`
- `apps/api/src/AI/InterpretationController.php`
- `apps/api/config/routes.php`
- `apps/api/tests/AI/GeminiInterpretationProviderTest.php`
- `apps/api/tests/AI/MockInterpretationProviderTest.php`
- `apps/api/tests/AI/InterpretationControllerTest.php`
- `apps/web/src/entities/interpretation/model.ts`
- `apps/web/src/entities/interpretation/api.ts`
- `apps/web/src/entities/interpretation/api.spec.ts`
- `apps/web/src/pages/consultations/ConsultationPage.vue`
- `apps/web/src/pages/consultations/ConsultationPage.spec.ts`

## Data / schema changes

None.

## Risks / open questions

- None currently open.
