<?php

declare(strict_types=1);

namespace App\Tests\Readings;

use App\Core\Config;
use App\Core\Database;
use App\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConsultationControllerTest extends TestCase
{
    private string $databasePath;
    private Kernel $kernel;

    protected function setUp(): void
    {
        $apiRoot = dirname(__DIR__, 2);
        $tempName = tempnam(sys_get_temp_dir(), 'yijing_test_');
        self::assertNotFalse($tempName);
        $this->databasePath = $tempName . '.sqlite';

        $config = new Config(['app_env' => 'testing', 'database_path' => $this->databasePath]);

        $pdo = Database::connect($config);
        $migrationsDir = $apiRoot . '/database/migrations';
        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            /** @var array{up: string} $migration */
            $migration = require $file;
            $pdo->exec($migration['up']);
        }

        $routeDefinitions = require $apiRoot . '/config/routes.php';
        $this->kernel = new Kernel($config, $routeDefinitions);
    }

    protected function tearDown(): void
    {
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testCreateWithThreeCoinsMethodPersistsAndReturns201(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertNotEmpty($body['id']);
        self::assertSame('Should I take the offer?', $body['question']);
        self::assertSame('three_coins', $body['method']);
        self::assertArrayHasKey('kingWenNumber', $body['primaryHexagram']);
        self::assertArrayHasKey('kingWenNumber', $body['resultingHexagram']);
        self::assertSame([], $body['notes']);
        self::assertSame([], $body['tags']);
    }

    public function testCreateWithRandomMethodReturns201(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Quick dev check?',
            'method' => 'random',
        ]);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateWithManualMethodBuildsTheExactHexagram(): void
    {
        $allYang = array_fill(0, 6, ['polarity' => 'yang', 'changing' => false]);

        $response = $this->postJson('/api/consultations', [
            'question' => 'Manual cast test',
            'method' => 'manual',
            'lines' => $allYang,
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(1, $body['primaryHexagram']['kingWenNumber']);
        self::assertSame(1, $body['resultingHexagram']['kingWenNumber']);
        self::assertSame([], $body['changingLinePositions']);
    }

    public function testCreateWithAllFiveContextFieldsReturnsThemExactlyAsSubmitted(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
            'context' => 'Been considering this for weeks.',
            'whatHappenedBefore' => 'Received the offer last Tuesday.',
            'whatUserWantsToUnderstand' => 'Whether the timing is right.',
            'backgroundInformation' => 'Currently employed elsewhere.',
            'initialInterpretation' => 'Feels like a yes.',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame('Been considering this for weeks.', $body['context']);
        self::assertSame('Received the offer last Tuesday.', $body['whatHappenedBefore']);
        self::assertSame('Whether the timing is right.', $body['whatUserWantsToUnderstand']);
        self::assertSame('Currently employed elsewhere.', $body['backgroundInformation']);
        self::assertSame('Feels like a yes.', $body['initialInterpretation']);
    }

    public function testCreateOmittingContextFieldsLeavesThemNull(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Quick one.',
            'method' => 'three_coins',
        ]);

        $body = $this->decode($response);

        self::assertNull($body['context']);
        self::assertNull($body['whatHappenedBefore']);
        self::assertNull($body['whatUserWantsToUnderstand']);
        self::assertNull($body['backgroundInformation']);
        self::assertNull($body['initialInterpretation']);
    }

    public function testCreateWithAContextFieldOverTheLengthLimitReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
            'context' => str_repeat('a', 5001),
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithEmptyQuestionReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => '   ',
            'method' => 'three_coins',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('error', $this->decode($response));
    }

    public function testCreateWithInvalidMethodReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'tarot',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodMissingLinesReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodWrongLineCountReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
            'lines' => array_fill(0, 5, ['polarity' => 'yang', 'changing' => false]),
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateWithManualMethodInvalidPolarityReturns422(): void
    {
        $lines = array_fill(0, 5, ['polarity' => 'yang', 'changing' => false]);
        $lines[] = ['polarity' => 'sideways', 'changing' => false];

        $response = $this->postJson('/api/consultations', [
            'question' => 'Valid question',
            'method' => 'manual',
            'lines' => $lines,
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testIndexReturnsAllConsultationsNewestFirst(): void
    {
        $this->postJson('/api/consultations', ['question' => 'First?', 'method' => 'random']);
        $this->postJson('/api/consultations', ['question' => 'Second?', 'method' => 'random']);

        $response = $this->kernel->handle(Request::create('/api/consultations', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(2, $body);
        self::assertSame('Second?', $body[0]['question']);
        self::assertSame('First?', $body[1]['question']);
    }

    public function testShowReturnsTheCreatedConsultation(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Round trip?',
            'method' => 'three_coins',
        ]));

        $response = $this->kernel->handle(Request::create('/api/consultations/' . $created['id'], 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame($created['id'], $body['id']);
        self::assertSame('Round trip?', $body['question']);
    }

    public function testShowReturns404ForAMissingConsultation(): void
    {
        $response = $this->kernel->handle(Request::create('/api/consultations/does-not-exist', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not Found'], $this->decode($response));
    }

    public function testShowIncludesRepeatsForSharedPrimaryResultingAndChangingLines(): void
    {
        $allYang = array_fill(0, 6, ['polarity' => 'yang', 'changing' => false]);
        $allYangLineOneChanging = $allYang;
        $allYangLineOneChanging[0] = ['polarity' => 'yang', 'changing' => true];

        $first = $this->decode($this->postJson('/api/consultations', [
            'question' => 'First?',
            'method' => 'manual',
            'lines' => $allYangLineOneChanging,
        ]));
        $second = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Same primary, resulting, and changing lines?',
            'method' => 'manual',
            'lines' => $allYangLineOneChanging,
        ]));
        // Different pattern entirely — must not show up in any repeats list for $first.
        $this->postJson('/api/consultations', [
            'question' => 'Unrelated?',
            'method' => 'manual',
            'lines' => array_fill(0, 6, ['polarity' => 'yin', 'changing' => false]),
        ]);

        $response = $this->kernel->handle(Request::create('/api/consultations/' . $first['id'], 'GET'));
        $body = $this->decode($response);

        self::assertSame(
            [$second['id']],
            array_column($body['repeats']['primaryHexagram'], 'id'),
        );
        self::assertSame(
            [$second['id']],
            array_column($body['repeats']['resultingHexagram'], 'id'),
        );
        self::assertSame(
            [$second['id']],
            array_column($body['repeats']['changingLines'], 'id'),
        );
    }

    public function testShowReturnsEmptyChangingLinesRepeatsWithoutMatchingOtherEmptySets(): void
    {
        $noChanging = array_fill(0, 6, ['polarity' => 'yang', 'changing' => false]);

        $first = $this->decode($this->postJson('/api/consultations', [
            'question' => 'First, no changing lines?',
            'method' => 'manual',
            'lines' => $noChanging,
        ]));
        $this->postJson('/api/consultations', [
            'question' => 'Second, also no changing lines?',
            'method' => 'manual',
            'lines' => $noChanging,
        ]);

        $body = $this->decode($this->kernel->handle(Request::create('/api/consultations/' . $first['id'], 'GET')));

        self::assertSame([], $body['repeats']['changingLines']);
    }

    public function testIndexAndUpdateResponsesDoNotIncludeRepeats(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'No repeats here?',
            'method' => 'random',
        ]));
        self::assertArrayNotHasKey('repeats', $created);

        $indexBody = $this->decode($this->kernel->handle(Request::create('/api/consultations', 'GET')));
        self::assertArrayNotHasKey('repeats', $indexBody[0]);

        $updated = $this->decode($this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'x']));
        self::assertArrayNotHasKey('repeats', $updated);
    }

    public function testUpdateAddsANote(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'after', 'text' => 'Took the offer.'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(1, $body['notes']);
        self::assertSame('after', $body['notes'][0]['label']);
        self::assertSame('Took the offer.', $body['notes'][0]['text']);
        self::assertSame([], $body['tags']);
    }

    public function testUpdateAddsATag(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertSame(['career'], $body['tags']);
        self::assertSame([], $body['notes']);
    }

    public function testUpdateAppliesBothANoteAndATagInOneRequest(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'before', 'text' => 'Feeling uncertain.'],
            'tag' => 'career',
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);

        self::assertCount(1, $body['notes']);
        self::assertSame(['career'], $body['tags']);
    }

    public function testUpdatePersistsAcrossAFreshFetch(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);

        $refetched = $this->decode($this->kernel->handle(
            Request::create('/api/consultations/' . $created['id'], 'GET'),
        ));

        self::assertSame(['career'], $refetched['tags']);
    }

    public function testUpdateAddingTheSameTagTwiceStaysDeduplicated(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);
        $response = $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['career'], $this->decode($response)['tags']);
    }

    public function testUpdateSetsAContextField(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'context' => 'Some context.',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Some context.', $this->decode($response)['context']);
    }

    public function testUpdateClearsAContextFieldWithExplicitNullWithoutTouchingOthers(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
            'context' => 'Original context.',
            'whatHappenedBefore' => 'Original before.',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'whatHappenedBefore' => null,
        ]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame('Original context.', $body['context'], 'untouched field must be preserved');
        self::assertNull($body['whatHappenedBefore']);
    }

    public function testUpdateWithOnlyAContextFieldDoesNotRequireNoteOrTag(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'initialInterpretation' => 'Leaning yes.',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Leaning yes.', $this->decode($response)['initialInterpretation']);
        self::assertSame([], $this->decode($response)['notes']);
    }

    public function testUpdateWithANonStringNonNullContextFieldReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], ['context' => 42]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateAddingANoteDoesNotDisturbExistingContextFields(): void
    {
        // Regression: withAddedNote()/withAddedTag() must preserve context fields
        // (Consultation::withAddedNote/withAddedTag, fixed as part of SPEC-019).
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
            'context' => 'Some context.',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'after', 'text' => 'Took the offer.'],
        ]);

        self::assertSame('Some context.', $this->decode($response)['context']);
    }

    public function testCreatedConsultationHasNoOutcome(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        self::assertNull($created['outcome']);
    }

    public function testUpdateRecordsAnOutcome(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'whatActuallyHappened' => 'Took the offer.',
            'outcome' => 'Started two weeks later, going well.',
            'reflection' => 'Glad I trusted the reading.',
        ]);

        self::assertSame(200, $response->getStatusCode());
        $outcome = $this->decode($response)['outcome'];

        self::assertSame('Took the offer.', $outcome['whatActuallyHappened']);
        self::assertSame('Started two weeks later, going well.', $outcome['outcome']);
        self::assertSame('Glad I trusted the reading.', $outcome['reflection']);
        self::assertNotEmpty($outcome['recordedAt']);
        self::assertNull($outcome['interpretationLens']);
        self::assertNull($outcome['interpretationSummary']);
    }

    public function testUpdateCanLinkAnOutcomeToAnInterpretation(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'whatActuallyHappened' => 'Took the offer.',
            'interpretationLens' => 'practical',
            'interpretationSummary' => 'The reading pointed toward decisive, grounded action.',
        ]);

        self::assertSame(200, $response->getStatusCode());
        $outcome = $this->decode($response)['outcome'];
        self::assertSame('practical', $outcome['interpretationLens']);
        self::assertSame(
            'The reading pointed toward decisive, grounded action.',
            $outcome['interpretationSummary'],
        );
    }

    public function testUpdateCanUnlinkAnOutcomeFromAnInterpretation(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], [
            'whatActuallyHappened' => 'Took the offer.',
            'interpretationLens' => 'practical',
            'interpretationSummary' => 'A summary.',
        ]);

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'interpretationLens' => null,
            'interpretationSummary' => null,
        ]);

        self::assertSame(200, $response->getStatusCode());
        $outcome = $this->decode($response)['outcome'];
        self::assertSame('Took the offer.', $outcome['whatActuallyHappened'], 'unrelated field must be preserved');
        self::assertNull($outcome['interpretationLens']);
        self::assertNull($outcome['interpretationSummary']);
    }

    public function testUpdateWithAnInvalidInterpretationLensReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'interpretationLens' => 'not-a-lens',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateTouchingOneOutcomeFieldPreservesTheOthers(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], [
            'whatActuallyHappened' => 'Took the offer.',
            'outcome' => 'Going well.',
        ]);

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'reflection' => 'Added later.',
        ]);

        $outcome = $this->decode($response)['outcome'];
        self::assertSame('Took the offer.', $outcome['whatActuallyHappened'], 'untouched field must be preserved');
        self::assertSame('Going well.', $outcome['outcome'], 'untouched field must be preserved');
        self::assertSame('Added later.', $outcome['reflection']);
    }

    public function testUpdateRecordingAnOutcomeDoesNotDisturbNotesTagsOrContext(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
            'context' => 'Some context.',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);
        $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'before', 'text' => 'Feeling uncertain.'],
        ]);

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'whatActuallyHappened' => 'Took the offer.',
        ]);

        $body = $this->decode($response);
        self::assertSame('Should I take the offer?', $body['question']);
        self::assertSame('Some context.', $body['context']);
        self::assertSame(['career'], $body['tags']);
        self::assertCount(1, $body['notes']);
        self::assertSame('Feeling uncertain.', $body['notes'][0]['text']);
    }

    public function testUpdateWithAnOutcomeFieldOverTheLengthLimitReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'outcome' => str_repeat('a', 5001),
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithOnlyAnOutcomeFieldDoesNotRequireNoteOrTag(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'reflection' => 'A quick reflection.',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('A quick reflection.', $this->decode($response)['outcome']['reflection']);
    }

    public function testCreatedConsultationHasNoFollowUpLinkOrFollowUps(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        self::assertNull($created['followUpTo']);
        self::assertSame([], $created['followUps']);
    }

    public function testCreateWithAFollowUpToConsultationIdResolvesTheSummary(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $followUpResponse = $this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
            'followUpToConsultationId' => $original['id'],
        ]);
        $followUp = $this->decode($followUpResponse);

        self::assertSame(201, $followUpResponse->getStatusCode());
        self::assertSame($original['id'], $followUp['followUpTo']['id']);
        self::assertSame('Should I take the offer?', $followUp['followUpTo']['question']);
    }

    public function testCreateWithAFollowUpToConsultationIdPointingAtNothingReturns422(): void
    {
        $response = $this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
            'followUpToConsultationId' => 'does-not-exist',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testGetOnAConsultationWithFollowUpsListsThem(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $followUp = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
            'followUpToConsultationId' => $original['id'],
        ]));

        $response = $this->kernel->handle(Request::create('/api/consultations/' . $original['id'], 'GET'));
        $body = $this->decode($response);

        self::assertCount(1, $body['followUps']);
        self::assertSame($followUp['id'], $body['followUps'][0]['id']);
        self::assertSame('Did I make the right call?', $body['followUps'][0]['question']);
    }

    public function testUpdateSetsAFollowUpLink(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => $original['id'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($original['id'], $this->decode($response)['followUpTo']['id']);
    }

    public function testUpdateClearsAFollowUpLink(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
            'followUpToConsultationId' => $original['id'],
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => null,
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($this->decode($response)['followUpTo']);
    }

    public function testUpdateWithAFollowUpToConsultationIdEqualToItsOwnIdReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => $created['id'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithAFollowUpToConsultationIdPointingAtNothingReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => 'does-not-exist',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithOnlyAFollowUpFieldDoesNotRequireNoteOrTag(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => $original['id'],
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateSettingAFollowUpLinkDoesNotDisturbOtherFields(): void
    {
        $original = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Did I make the right call?',
            'method' => 'three_coins',
            'context' => 'Some context.',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'followUpToConsultationId' => $original['id'],
        ]);

        $body = $this->decode($response);
        self::assertSame('Did I make the right call?', $body['question']);
        self::assertSame('Some context.', $body['context']);
        self::assertSame(['career'], $body['tags']);
    }

    public function testCreatedConsultationIsNotFavoriteByDefault(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));

        self::assertFalse($created['favorite']);
    }

    public function testUpdateSetsAndClearsFavorite(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));

        $favorited = $this->decode($this->patchJson('/api/consultations/' . $created['id'], ['favorite' => true]));
        self::assertTrue($favorited['favorite']);

        $unfavorited = $this->decode(
            $this->patchJson('/api/consultations/' . $created['id'], ['favorite' => false]),
        );
        self::assertFalse($unfavorited['favorite']);
    }

    public function testUpdateWithANonBooleanFavoriteReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], ['favorite' => 'yes']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithOnlyFavoriteDoesNotRequireNoteOrTag(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], ['favorite' => true]);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateSettingFavoriteDoesNotDisturbOtherFields(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);

        $updated = $this->decode(
            $this->patchJson('/api/consultations/' . $created['id'], ['favorite' => true]),
        );

        self::assertSame(['career'], $updated['tags']);
        self::assertTrue($updated['favorite']);
    }

    public function testImportRoundTripsAFullExportedConsultation(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'manual',
            'lines' => array_fill(0, 6, ['polarity' => 'yang', 'changing' => true]),
            'context' => 'Some context.',
        ]));
        $this->patchJson('/api/consultations/' . $created['id'], ['tag' => 'career']);
        $this->patchJson('/api/consultations/' . $created['id'], ['favorite' => true]);
        $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'after', 'text' => 'Went well.'],
        ]);
        $this->patchJson('/api/consultations/' . $created['id'], ['whatActuallyHappened' => 'Took it.']);
        $exported = $this->decode($this->kernel->handle(Request::create('/api/consultations', 'GET')))[0];

        // Restore into a fresh database.
        $freshDatabasePath = tempnam(sys_get_temp_dir(), 'yijing_test_') . '.sqlite';
        $freshConfig = new Config(['app_env' => 'testing', 'database_path' => $freshDatabasePath]);
        $freshPdo = Database::connect($freshConfig);
        $apiRoot = dirname(__DIR__, 2);
        foreach (glob($apiRoot . '/database/migrations/*.php') ?: [] as $file) {
            /** @var array{up: string} $migration */
            $migration = require $file;
            $freshPdo->exec($migration['up']);
        }
        $freshKernel = new Kernel($freshConfig, require $apiRoot . '/config/routes.php');

        $importResponse = $freshKernel->handle(Request::create(
            '/api/consultations/import',
            'POST',
            content: json_encode([$exported], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(201, $importResponse->getStatusCode());
        self::assertSame(['imported' => 1], $this->decode($importResponse));

        $restored = $this->decode($freshKernel->handle(Request::create(
            '/api/consultations/' . $exported['id'],
            'GET',
        )));

        self::assertSame($exported['id'], $restored['id']);
        self::assertSame($exported['createdAt'], $restored['createdAt']);
        self::assertSame($exported['question'], $restored['question']);
        self::assertSame($exported['context'], $restored['context']);
        self::assertSame($exported['tags'], $restored['tags']);
        self::assertSame($exported['notes'], $restored['notes']);
        self::assertTrue($restored['favorite']);
        self::assertSame('Took it.', $restored['outcome']['whatActuallyHappened']);

        // Release every reference to the fresh PDO connection first — on Windows, SQLite keeps
        // the file locked until the last PDO object is garbage-collected, so unlink() can
        // otherwise fail with "Resource temporarily unavailable".
        unset($freshKernel, $freshPdo);
        gc_collect_cycles();
        if (is_file($freshDatabasePath)) {
            unlink($freshDatabasePath);
        }
    }

    public function testImportRejectsTheWholeBatchWhenAnIdAlreadyExists(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'random',
        ]));
        $exported = $this->decode($this->kernel->handle(Request::create('/api/consultations', 'GET')))[0];

        $response = $this->kernel->handle(Request::create(
            '/api/consultations/import',
            'POST',
            content: json_encode([$exported], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
        // Nothing was duplicated — still exactly the one original consultation.
        $all = $this->decode($this->kernel->handle(Request::create('/api/consultations', 'GET')));
        self::assertCount(1, $all);
        self::assertSame($created['id'], $all[0]['id']);
    }

    public function testImportRejectsAnUnresolvableFollowUpLink(): void
    {
        $item = [
            'id' => 'orphan-1',
            'question' => 'Orphaned follow-up?',
            'method' => 'three_coins',
            'primaryHexagram' => ['kingWenNumber' => 1],
            'changingLinePositions' => [],
            'resultingHexagram' => ['kingWenNumber' => 1],
            'createdAt' => '2026-08-14T10:00:00+00:00',
            'notes' => [],
            'tags' => [],
            'favorite' => false,
            'followUpTo' => ['id' => 'does-not-exist', 'question' => 'x'],
        ];

        $response = $this->kernel->handle(Request::create(
            '/api/consultations/import',
            'POST',
            content: json_encode([$item], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testImportOfAnEmptyArrayIsANoOpSuccess(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/consultations/import',
            'POST',
            content: '[]',
        ));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['imported' => 0], $this->decode($response));
    }

    public function testImportWithMalformedJsonReturns422(): void
    {
        $response = $this->kernel->handle(Request::create('/api/consultations/import', 'POST', content: 'not json'));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testImportWithANonArrayBodyReturns422(): void
    {
        $response = $this->kernel->handle(Request::create(
            '/api/consultations/import',
            'POST',
            content: '{"not": "an array"}',
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithNeitherNoteNorTagReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], []);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithInvalidNoteLabelReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'sometime', 'text' => 'Hmm.'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithEmptyNoteTextReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], [
            'note' => ['label' => 'before', 'text' => '   '],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateWithEmptyTagReturns422(): void
    {
        $created = $this->decode($this->postJson('/api/consultations', [
            'question' => 'Should I take the offer?',
            'method' => 'three_coins',
        ]));

        $response = $this->patchJson('/api/consultations/' . $created['id'], ['tag' => '   ']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateReturns404ForAMissingConsultationBeforeValidatingTheBody(): void
    {
        $response = $this->patchJson('/api/consultations/does-not-exist', []);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Not Found'], $this->decode($response));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $uri, array $body): Response
    {
        $request = Request::create($uri, 'POST', content: json_encode($body, JSON_THROW_ON_ERROR));

        return $this->kernel->handle($request);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patchJson(string $uri, array $body): Response
    {
        $request = Request::create($uri, 'PATCH', content: json_encode($body, JSON_THROW_ON_ERROR));

        return $this->kernel->handle($request);
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        /** @var array<mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
