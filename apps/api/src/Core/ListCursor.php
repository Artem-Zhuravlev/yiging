<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Opaque pagination cursor for the newest-first list endpoints — consultations and journal
 * (SPEC-041). Encodes the `(created_at, rowid)` of the last row of a page; the next page is
 * every row strictly after it
 * in `created_at DESC, rowid DESC` order — `rowid` breaks ties between rows sharing a
 * `created_at` second, so no row is skipped or repeated across a page boundary.
 *
 * The wire form is just base64 of `"{createdAtAtom}|{rowid}"` — deliberately not signed or
 * encrypted: it carries no secret, only a position the client already effectively knows.
 */
final class ListCursor
{
    public static function encode(string $createdAtAtom, int $rowid): string
    {
        return base64_encode($createdAtAtom . '|' . $rowid);
    }

    /**
     * @return array{0: string, 1: int} [createdAtAtom, rowid]
     *
     * @throws \InvalidArgumentException if $token is not a cursor this class produced
     */
    public static function decode(string $token): array
    {
        $decoded = base64_decode($token, true);

        if ($decoded === false || !str_contains($decoded, '|')) {
            throw new \InvalidArgumentException('Malformed pagination cursor.');
        }

        [$createdAtAtom, $rowid] = explode('|', $decoded, 2);

        if ($createdAtAtom === '' || !ctype_digit($rowid)) {
            throw new \InvalidArgumentException('Malformed pagination cursor.');
        }

        return [$createdAtAtom, (int) $rowid];
    }
}
