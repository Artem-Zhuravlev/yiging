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
}
