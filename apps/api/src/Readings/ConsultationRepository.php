<?php

declare(strict_types=1);

namespace App\Readings;

interface ConsultationRepository
{
    public function save(Consultation $consultation): void;

    public function findById(string $id): ?Consultation;

    /**
     * @return list<Consultation> ordered newest-first (createdAt descending)
     */
    public function findAll(): array;

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
