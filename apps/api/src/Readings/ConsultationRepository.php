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
}
