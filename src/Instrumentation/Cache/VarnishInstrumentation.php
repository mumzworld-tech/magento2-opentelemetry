<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Cache;

use Magento\CacheInvalidate\Model\PurgeCache;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class VarnishInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.varnish';
    private const SPAN_NAME_PREFIX = 'Cache: Varnish:';

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
        self::instrumentVarnishPurgeCache();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentVarnishPurgeCache(): void
    {
        hook(
            PurgeCache::class,
            'sendPurgeRequest',
            static function (
                PurgeCache  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $tags = $params[0] ?? [];
                if (is_string($tags)) {
                    $tags = [$tags];
                }
                $spanName = sprintf('%s PurgeCache', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setAttribute('magento.cache.varnish.purge.tags', self::saferJsonEncode($tags));

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                PurgeCache $subject,
                array      $params,
                mixed      $returnValue,
                ?Throwable $exception,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $scope->detach();

                $span = Span::fromContext($scope->context());
                if ($exception) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }
                $span->setAttribute('magento.cache.varnish.purge.result', $returnValue ? 'success' : 'failed');
                $span->end();
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
