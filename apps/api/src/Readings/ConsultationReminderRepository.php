<?php

declare(strict_types=1);

namespace App\Readings;

/**
 * The per-consultation "record the outcome" reminder (SPEC-054), kept in its own table with its
 * own lifecycle (set / change / snooze / auto-clear once an outcome is recorded) — deliberately
 * NOT a field on the immutable {@see Consultation} aggregate. At most one reminder per
 * consultation.
 */
interface ConsultationReminderRepository
{
    /**
     * Upserts the reminder for $consultationId. On replace, $createdAt is ignored and the
     * original creation time is kept. Caller MUST have verified the consultation exists.
     */
    public function set(
        string $consultationId,
        \DateTimeImmutable $remindAt,
        \DateTimeImmutable $createdAt,
    ): void;

    /**
     * Removes the reminder for $consultationId if there is one. A no-op when there isn't.
     */
    public function clear(string $consultationId): void;

    public function findRemindAt(string $consultationId): ?\DateTimeImmutable;

    /**
     * @return list<DueReminder> every reminder whose remind_at is at or before $now AND whose
     *     consultation has no recorded outcome, ordered by remind_at ascending
     */
    public function findDue(\DateTimeImmutable $now): array;
}
