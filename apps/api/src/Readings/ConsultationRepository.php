<?php

declare(strict_types=1);

namespace App\Readings;

interface ConsultationRepository
{
    public function save(Consultation $consultation): void;

    public function findById(string $id): ?Consultation;

    /**
     * @return list<Consultation> ordered newest-first (createdAt descending)
     *
     * Full hydration of the entire history — used only by the backup export (SPEC-028/041).
     * The History page uses {@see findListPage()} instead.
     */
    public function findAll(): array;

    /**
     * One page of the lean, filterable consultation list (SPEC-041). Bounded query cost
     * regardless of history size: one page query plus one batched tag query, no per-row
     * notes/outcome/follow-up hydration.
     */
    public function findListPage(ConsultationListQuery $query): ConsultationListPage;

    /**
     * @return list<string> every distinct tag name currently applied to a consultation, sorted
     */
    public function allTagNames(): array;

    /**
     * @return list<array{name: string, count: int}> every used tag with the number of
     *     consultations carrying it, sorted by name (SPEC-050)
     */
    public function allTagsWithCounts(): array;

    public function tagExists(string $name): bool;

    /**
     * Renames tag $from to $to (SPEC-050). If a tag named $to already exists, the two are
     * merged: every consultation on $from is moved onto $to (no duplicate links) and the $from
     * tag row is removed. One transaction. Caller MUST have verified $from exists.
     */
    public function renameOrMergeTag(string $from, string $to): void;

    /**
     * Deletes tag $name and every consultation's link to it (via ON DELETE CASCADE). No
     * consultation row is affected. Caller MUST have verified the tag exists.
     */
    public function deleteTag(string $name): void;

    public function findSummaryById(string $id): ?ConsultationSummary;

    /**
     * @return list<ConsultationSummary> every consultation whose followUpToConsultationId
     *     points at $consultationId, ordered oldest-first (createdAt ascending)
     */
    public function findFollowUpSummaries(string $consultationId): array;

    /**
     * @return list<ConsultationSummary> other consultations (excluding $excludeId) sharing this
     *     primary hexagram, ordered newest-first (createdAt descending)
     */
    public function findByPrimaryHexagramNumber(int $kingWenNumber, string $excludeId): array;

    /**
     * @return list<ConsultationSummary> other consultations (excluding $excludeId) sharing this
     *     resulting hexagram, ordered newest-first (createdAt descending)
     */
    public function findByResultingHexagramNumber(int $kingWenNumber, string $excludeId): array;

    /**
     * @param list<int> $positions
     *
     * @return list<ConsultationSummary> other consultations (excluding $excludeId) whose changing
     *     line positions are exactly this set, ordered newest-first (createdAt descending)
     */
    public function findByChangingLinePositions(array $positions, string $excludeId): array;

    public function existsById(string $id): bool;

    /**
     * Saves an entire pre-validated import batch (SPEC-028) in one transaction: every
     * consultation is inserted with its follow-up link cleared first, then every original link is
     * restored in a second pass, so cross-references within the same batch resolve regardless of
     * array order. Callers MUST have already validated ids are unique, don't already exist, and
     * every followUpToConsultationId resolves — this method does no validation of its own.
     *
     * @param list<Consultation> $consultations
     */
    public function saveImportBatch(array $consultations): void;
}
