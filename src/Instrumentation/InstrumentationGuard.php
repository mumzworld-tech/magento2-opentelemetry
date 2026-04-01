<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation;

use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\SdkAutoloader;
use Throwable;

/**
 * Guards against inappropriate OpenTelemetry instrumentation in Magento
 */
class InstrumentationGuard
{
    /**
     * Instrumentation name used for SDK checks
     */
    private const INSTRUMENTATION_NAME = 'magento2';

    /**
     * List of URLs that should be excluded from instrumentation
     */
    private const EXCLUDED_URLS = [
        'health_check.php',
        'get.php',
        'static.php',
        'cron.php'
    ];

    /**
     * Development/testing scripts that should be excluded
     */
    private const EXCLUDED_SCRIPTS = [
        'phpstan',
        'phpunit'
    ];

    /**
     * Paths that are allowed to be instrumented in web context
     */
    private const ALLOWED_PATHS = [
        '/rest/',
        '/admin/',
        '/backend/',
        '/graphql'
    ];

    /**
     * Determines if the current context is eligible for OpenTelemetry instrumentation
     *
     * @return bool True if instrumentation should be applied, false otherwise
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function isInstrumentationEligible(): bool
    {
        try {
            self::setExcludedUrls();

            if (!self::isInstrumentationEnabled() || self::isExcludedScript() || !self::isAllowedRequest()) {
                return false;
            }

            return true;
        } catch (Throwable $e) {

            return false;
        }
    }

    /**
     * Sets excluded URLs as an environment variable for OpenTelemetry.
     *
     * @return void
     */
    private static function setExcludedUrls(): void
    {
        $urlsString = implode(',', self::EXCLUDED_URLS);
        //phpcs:ignore Magento2.Functions.DiscouragedFunction
        putenv(
            sprintf('OTEL_PHP_EXCLUDED_URLS=%s', $urlsString)
        );
    }

    /**
     * Checks if instrumentation is enabled and available
     *
     * @return bool
     */
    private static function isInstrumentationEnabled(): bool
    {
        return class_exists(Sdk::class)
            && !Sdk::isInstrumentationDisabled(self::INSTRUMENTATION_NAME)
            && \extension_loaded('opentelemetry')
            && SdkAutoloader::isEnabled()
            && !SdkAutoloader::isExcludedUrl();
    }

    /**
     * Checks if the current script is in the excluded list
     *
     * @return bool
     */
    private static function isExcludedScript(): bool
    {
        //phpcs:ignore
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        foreach (self::EXCLUDED_SCRIPTS as $script) {
            if (str_contains($scriptName, $script)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the current request should be instrumented
     *
     * @return bool
     */
    private static function isAllowedRequest(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        //phpcs:ignore
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        foreach (self::ALLOWED_PATHS as $path) {
            if (self::matchesAllowedPath($requestUri, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given request URI matches an allowed path.
     *
     * - If the path is "/rest/", it checks whether "/rest/" appears anywhere in the request URI.
     * - For other paths, it checks if the request URI starts with the specified path.
     *
     * @param string $requestUri The current request URI.
     * @param string $path The allowed path to check against.
     * @return bool True if the request URI matches the path, false otherwise.
     */
    private static function matchesAllowedPath(string $requestUri, string $path): bool
    {
        if ($path === '/rest/') {
            return str_contains($requestUri, $path);
        }

        return str_starts_with($requestUri, $path);
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
