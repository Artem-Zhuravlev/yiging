<?php

declare(strict_types=1);

namespace App\Core;

use Symfony\Component\HttpFoundation\Request;

/**
 * The locale a read request asks classical text to be returned in (SPEC-057): `?lang=uk` for
 * Ukrainian, anything else (including absent or unrecognised) for the canonical English.
 */
final class RequestLocale
{
    public static function from(Request $request): string
    {
        return $request->query->get('lang') === 'uk' ? 'uk' : 'en';
    }
}
