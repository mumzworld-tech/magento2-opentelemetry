<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util;

/**
 * Keeps track of how many spans are being created per key (like Redis, SQL, etc),
 * and ensures we don’t go overboard by applying a soft cap.
 *
 * Can be used across any instrumentation logic where you want to avoid
 * excessive span creation that could affect performance.
 */
class SpanLimiter
{
    public const DEFAULT_LIMIT = 250;

    /** @var SpanLimiter|null Singleton instance */
    private static ?SpanLimiter $instance = null;

    /** @var array<string, int> Limits for each span key */
    private array $limits = [];

    /** @var array<string, int> Counter of how many spans were created per key */
    private array $counters = [];

    /** @var array<string, bool> Tracks whether span creation was skipped for a key */
    private array $skipRegistry = [];

    /**
     * Singleton instance.
     *
     * @return SpanLimiter
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
    //phpcs:enable Magento2.Functions.StaticFunction

    /**
     * Set the span limits per key (with a fallback to a default value).
     *
     * @param array $limits
     * @return void
     */
    public function setLimits(array $limits): void
    {
        $this->limits = $limits + ['default' => self::DEFAULT_LIMIT];
    }

    /**
     * Check if we should create a span for this key.
     * Increments the internal counter if allowed.
     *
     * @param string $key
     * @return bool
     */
    public function shouldStart(string $key): bool
    {
        $limit = $this->limits[$key] ?? $this->limits['default'];
        $count = $this->counters[$key] ?? 0;

        if ($count >= $limit) {
            $this->skipRegistry[$key] = true;
            return false;
        }

        $this->counters[$key] = $count + 1;
        $this->skipRegistry[$key] = false;
        return true;
    }

    /**
     * Check if span creation was skipped for the given key.
     *
     * @param string $key
     * @return bool
     */
    public function wasSkipped(string $key): bool
    {
        return $this->skipRegistry[$key] ?? false;
    }

    /**
     * Clear tracking for a specific key.
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        unset($this->skipRegistry[$key]);
    }

    /**
     * Get current span counts for all keys.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        return $this->counters;
    }
}
