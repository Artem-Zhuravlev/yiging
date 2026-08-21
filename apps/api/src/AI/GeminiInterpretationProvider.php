<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Real InterpretationProvider backed by Google's Gemini API (via GeminiClient). Every field
 * except sourceReferences is genuinely AI-generated, grounded strictly in the given context's
 * own canonical text (the prompt includes nothing else) - sourceReferences is always
 * $context->defaultSourceReferences(), never taken from the model's response, so a citation
 * can never be invented (see InterpretationContext::defaultSourceReferences()'s docblock).
 */
final class GeminiInterpretationProvider implements InterpretationProvider
{
    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'coreTheme' => ['type' => 'string'],
            'situation' => ['type' => 'string'],
            // Gemini's response_schema is a Protobuf-backed OpenAPI subset, not plain JSON
            // Schema: a nullable field is `nullable: true` alongside one `type`, not JSON
            // Schema's `type: [x, "null"]` array form (confirmed via a real 400 response before
            // this fix — "Proto field is not repeating, cannot start list").
            'changingLineMeaning' => ['type' => 'string', 'nullable' => true],
            'transition' => ['type' => 'string', 'nullable' => true],
            'practicalReflection' => ['type' => 'string'],
            'uncertainties' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['summary', 'coreTheme', 'situation', 'practicalReflection', 'uncertainties'],
    ];

    private const REQUIRED_STRING_FIELDS = ['summary', 'coreTheme', 'situation', 'practicalReflection'];

    private const FOLLOW_UP_RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'answer' => ['type' => 'string'],
        ],
        'required' => ['answer'],
    ];

    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function interpret(
        InterpretationContext $context,
        InterpretationLens $lens,
        InterpretationProfile $profile,
    ): Interpretation {
        $data = $this->client->generateJson($this->buildPrompt($context, $lens, $profile), self::RESPONSE_SCHEMA);

        foreach (self::REQUIRED_STRING_FIELDS as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new InterpretationProviderException(
                    "Gemini response is missing a valid '{$field}' field.",
                );
            }
        }

        if (!isset($data['uncertainties']) || !is_array($data['uncertainties'])) {
            throw new InterpretationProviderException("Gemini response is missing a valid 'uncertainties' field.");
        }

        return new Interpretation(
            summary: $data['summary'],
            coreTheme: $data['coreTheme'],
            situation: $data['situation'],
            changingLineMeaning: $this->nullableString($data['changingLineMeaning'] ?? null),
            transition: $this->nullableString($data['transition'] ?? null),
            practicalReflection: $data['practicalReflection'],
            uncertainties: array_values(array_map(strval(...), $data['uncertainties'])),
            sourceReferences: $context->defaultSourceReferences(),
        );
    }

    /**
     * @param list<ConversationExchange> $history
     */
    public function answerFollowUp(
        InterpretationContext $context,
        array $history,
        string $question,
        InterpretationProfile $profile,
    ): FollowUpAnswer {
        $prompt = $this->buildFollowUpPrompt($context, $history, $question, $profile);
        $data = $this->client->generateJson($prompt, self::FOLLOW_UP_RESPONSE_SCHEMA);

        if (!isset($data['answer']) || !is_string($data['answer']) || trim($data['answer']) === '') {
            throw new InterpretationProviderException("Gemini response is missing a valid 'answer' field.");
        }

        return new FollowUpAnswer($data['answer'], $context->defaultSourceReferences());
    }

    /**
     * @param list<ConversationExchange> $history
     */
    private function buildFollowUpPrompt(
        InterpretationContext $context,
        array $history,
        string $question,
        InterpretationProfile $profile,
    ): string {
        $lines = $this->contextGroundingLines($context);

        if ($history !== []) {
            $lines[] = '';
            $lines[] = 'Prior conversation about this reading:';
            foreach ($history as $exchange) {
                $lines[] = "Q: {$exchange->question}";
                $lines[] = "A: {$exchange->answer}";
            }
        }

        $lines[] = '';
        $lines[] = "New question: {$question}";
        $lines[] = '';
        $lines[] = 'Answer the new question in your own words, grounded strictly in the canonical '
            . 'text and conversation above - do not invent classical text, quotes, or hexagram '
            . 'facts beyond what is given here (answer).';

        $this->appendProfileInstruction($lines, $profile);

        return implode("\n", $lines);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The context-grounding block shared by every prompt this provider builds (interpret() and
     * answerFollowUp()) - factored out so both can never drift apart in what canonical text they
     * ground on, which would otherwise risk one of the two silently inventing beyond the given
     * context while the other stays honest.
     *
     * @return list<string>
     */
    private function contextGroundingLines(InterpretationContext $context): array
    {
        $hasChangingLines = $context->changingLinePositions !== [];

        $lines = [
            'You are assisting with an I Ching (Yijing) consultation. Ground your response '
                . 'strictly in the canonical text provided below. Do not invent additional classical '
                . 'text, quotes, or hexagram facts beyond what is given here.',
            '',
            "Question: {$context->question}",
            '',
            sprintf(
                'Primary hexagram: %d. %s (%s)',
                $context->primaryHexagram->kingWenNumber,
                $context->primaryHexagram->chineseName,
                $context->primaryHexagram->pinyin,
            ),
            "Judgment: {$context->primaryHexagram->judgment}",
            "Image: {$context->primaryHexagram->image}",
        ];

        if ($hasChangingLines) {
            $lines[] = '';
            $lines[] = 'Changing lines:';
            foreach ($context->changingLineStatements as $position => $statement) {
                $lines[] = "Line {$position}: {$statement}";
            }
            $lines[] = '';
            $lines[] = sprintf(
                'Resulting hexagram: %d. %s (%s)',
                $context->resultingHexagram->kingWenNumber,
                $context->resultingHexagram->chineseName,
                $context->resultingHexagram->pinyin,
            );
            $lines[] = "Resulting judgment: {$context->resultingHexagram->judgment}";
        } else {
            $lines[] = '';
            $lines[] = 'There are no changing lines for this reading.';
        }

        if ($context->userNotes !== []) {
            $lines[] = '';
            $lines[] = 'Notes the person has already written about this consultation:';
            foreach ($context->userNotes as $note) {
                $lines[] = "- {$note}";
            }
        }

        return $lines;
    }

    private function buildPrompt(
        InterpretationContext $context,
        InterpretationLens $lens,
        InterpretationProfile $profile,
    ): string {
        $hasChangingLines = $context->changingLinePositions !== [];
        $lines = $this->contextGroundingLines($context);

        $lines[] = '';
        $lines[] = 'Provide, in your own words but grounded in the text above: a one-sentence summary '
            . 'connecting the hexagram to the question (summary); the core theme of the primary '
            . 'hexagram (coreTheme); the situation it describes (situation); '
            . ($hasChangingLines
                ? 'what the changing lines mean for this question and the transition toward the '
                    . 'resulting hexagram (changingLineMeaning, transition)'
                : 'changingLineMeaning and transition should be null, since there are no changing lines')
            . '; a practical reflection connecting this to the actual question asked '
            . '(practicalReflection); and genuine uncertainties about this interpretation '
            . '(uncertainties) - name real limits (ambiguity in the text, that this is AI-assisted '
            . 'rather than authoritative, that personal judgment still matters), not a generic '
            . 'disclaimer.';

        $lensInstruction = $this->lensInstruction($lens);

        if ($lensInstruction !== null) {
            $lines[count($lines) - 1] .= ' ' . $lensInstruction;
        }

        $this->appendProfileInstruction($lines, $profile);

        return implode("\n", $lines);
    }

    private function lensInstruction(InterpretationLens $lens): ?string
    {
        return match ($lens) {
            // General adds nothing — the prompt for this case must stay byte-identical to
            // this provider's pre-SPEC-033 behavior.
            InterpretationLens::General => null,
            InterpretationLens::Psychological => 'Focus especially on the psychological and '
                . 'internal dimension of this reading — emotions, mindset, inner conflict, and '
                . 'self-perception relevant to the question.',
            InterpretationLens::Practical => 'Focus especially on concrete, actionable practical '
                . 'guidance — what to actually do, in the real world, in response to the question.',
            InterpretationLens::Symbolic => 'Focus especially on the symbolic and archetypal '
                . 'dimension of this reading — the imagery, metaphor, and traditional symbolic '
                . 'associations of the hexagram and lines, and what they represent beyond the '
                . 'literal.',
        };
    }

    /**
     * Appends one "Personal preferences:" line naming only the non-default aspects of the
     * profile (SPEC-035) - an all-default profile appends nothing, keeping the prompt
     * byte-identical to this provider's pre-SPEC-035 form (REQ-PROFILE-003).
     *
     * @param list<string> $lines
     */
    private function appendProfileInstruction(array &$lines, InterpretationProfile $profile): void
    {
        if ($profile->isDefault()) {
            return;
        }

        $parts = [];

        if ($profile->tone !== Tone::Neutral) {
            $parts[] = 'write in a ' . $this->toneDescription($profile->tone) . ' tone';
        }

        if ($profile->length !== ResponseLength::Standard) {
            $parts[] = $this->lengthDescription($profile->length);
        }

        if ($profile->notes !== null) {
            $parts[] = 'also take into account this personal preference: "' . $profile->notes . '"';
        }

        $lines[] = '';
        $lines[] = 'Personal preferences: ' . implode('; ', $parts) . '.';
    }

    private function toneDescription(Tone $tone): string
    {
        return match ($tone) {
            Tone::Neutral => 'neutral',
            Tone::Formal => 'formal and precise',
            Tone::Casual => 'casual and conversational',
            Tone::Poetic => 'poetic and richly imagistic',
        };
    }

    private function lengthDescription(ResponseLength $length): string
    {
        return match ($length) {
            ResponseLength::Standard => 'keep the response at a standard length',
            ResponseLength::Brief => 'keep the response brief and concise',
            ResponseLength::Detailed => 'provide a detailed, thorough response',
        };
    }
}
