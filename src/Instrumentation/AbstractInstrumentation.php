<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * Provides an abstract class for OpenTelemetry instrumentation in Magento.
 * It includes utility functions for tracing spans, processing exceptions,
 * handling URL segments, safely encoding JSON, etc.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\SpanInterface;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Traits\SpanBuilderTrait;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Traits\AttributeSanitizerTrait;

/**
 * AbstractInstrumentation provides core tracing functionalities for OpenTelemetry integration in Magento.
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractInstrumentation
{
    use AttributeSanitizerTrait;
    use SpanBuilderTrait;

    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento';

    /**
     * Get the name of the instrumentation.
     *
     * @return string
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    abstract protected static function getInstrumentationName(): string;

    /**
     * Register the instrumentation hooks.
     *
     * @return void
     */
    abstract public static function register(): void;

    /**
     * Updates the name of the root span.
     *
     * @param string $name
     * @return SpanInterface|null
     */
    protected static function updateRootSpanName(string $name): ?SpanInterface
    {
        $rootSpan = LocalRootSpan::current();
        if ($rootSpan && !empty($name)) {
            $rootSpan->updateName($name);
        }
        return $rootSpan;
    }

    /**
     * Extracts the class name from a fully qualified class name (FQCN).
     * Also removes any "Interceptor" suffix if present.
     *
     * @param string $classNameString The fully qualified class name.
     *
     * @return string The extracted class name.
     */
    protected static function extractClassName(string $classNameString): string
    {
        $classNameString = str_replace("\Interceptor", "", $classNameString);
        $parts = explode("\\", $classNameString);
        return end($parts);
    }

    /**
     * Extracts a name like ProductCollection from a class name ending in
     * ...\Collection or ...\Collection\Interceptor
     *
     * @param string $className Fully qualified class name
     * @return string|null The extracted name or null if it doesn't match pattern
     */
    protected static function extractCollectionName(string $className): ?string
    {
        // Normalize: trim trailing \Interceptor if exists
        if (str_ends_with($className, "\\Interceptor")) {
            $className = substr($className, 0, -strlen("\\Interceptor"));
        }

        $parts = explode("\\", $className);

        // Must end with 'Collection' and have at least 2 parts to extract entity
        $last = array_pop($parts);
        if ($last !== "Collection" || empty($parts)) {
            return null;
        }

        $entity = array_pop($parts);
        return $entity . "Collection";
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
