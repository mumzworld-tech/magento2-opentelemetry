<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Cache;

use Magento\PageCache\Observer\InvalidateCache;
use Magento\CacheInvalidate\Observer\FlushAllCacheObserver;
use Magento\PageCache\Observer\FlushFormKey\Interceptor as FlushFormKeyInterceptor;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class CacheInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.cache';
    private const SPAN_NAME_PREFIX = 'Cache:';
    private const INSTRUMENTED_CLASSES = [
        FlushFormKeyInterceptor::class,
        FlushAllCacheObserver::class,
        InvalidateCache::class
    ];

    /**
     * @inheritdoc
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    protected static function getInstrumentationName(): string
    {
        return self::INSTRUMENTATION_NAME;
    }

    /**
     * @inheritdoc
     */
    public static function register(): void
    {
        self::instrumentCacheInvalidation();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentCacheInvalidation(): void
    {
        foreach (self::INSTRUMENTED_CLASSES as $class) {
            hook(
                $class,
                'execute',
                static function (
                    object  $subject,
                    array   $params,
                    string  $class,
                    string  $function,
                    ?string $filename,
                    ?int    $lineno,
                ) {
                    $spanName = sprintf('%s %s', self::SPAN_NAME_PREFIX, self::extractClassName($class));
                    $builder = self::createSpanBuilder(
                        $spanName,
                        $function,
                        $class,
                        $filename,
                        $lineno,
                    );

                    self::startSpanAndAttachToContext($builder);
                },
                static function (
                    object     $subject,
                    array      $params,
                    mixed      $returnValue,
                    ?Throwable $exception,
                ) {
                    self::endSpan($exception);
                },
            );
        }
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
