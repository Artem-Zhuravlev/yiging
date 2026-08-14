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
            'changingLineMeaning' => ['type' => ['string', 'null']],
            'transition' => ['type' => ['string', 'null']],
            'practicalReflection' => ['type' => 'string'],
            'uncertainties' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['summary', 'coreTheme', 'situation', 'practicalReflection', 'uncertainties'],
    ];

    private const REQUIRED_STRING_FIELDS = ['summary', 'coreTheme', 'situation', 'practicalReflection'];

    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function interpret(InterpretationContext $context): Interpretation
    {
        $data = $this->client->generateJson($this->buildPrompt($context), self::RESPONSE_SCHEMA);

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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function buildPrompt(InterpretationContext $context): string
    {
        $hasChangingLines = $context->changingLinePositions !== [];

        $lines = [
            'You are assisting with an I Ching (Yijing) consultation. Ground your interpretation '
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

        return implode("\n", $lines);
    }
}
