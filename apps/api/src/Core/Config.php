<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function fromEnv(string $rootDir): self
    {
        $envPath = $rootDir . '/.env';
        $env = [];

        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }

        $env += getenv() ?: [];

        $databasePath = $env['DATABASE_PATH'] ?? './database/database.sqlite';

        return new self([
            'app_env' => $env['APP_ENV'] ?? 'production',
            'database_path' => self::resolvePath($databasePath, $rootDir),
            'ai_provider' => $env['AI_PROVIDER'] ?? 'mock',
            'ai_api_key' => $env['AI_API_KEY'] ?? '',
            'ai_model' => $env['AI_MODEL'] ?? 'gemini-3.6-flash',
            'ai_rate_limit_max' => (int) ($env['AI_RATE_LIMIT_MAX'] ?? 20),
            'ai_rate_limit_window_seconds' => (int) ($env['AI_RATE_LIMIT_WINDOW_SECONDS'] ?? 3600),
        ]);
    }

    private static function resolvePath(string $path, string $rootDir): string
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : $rootDir . '/' . ltrim($path, './');
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    /**
     * $default applies only when $key is genuinely absent (never configured at all) - an
     * explicitly-set value of 0 is honored, not treated as "unset."
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }
}
