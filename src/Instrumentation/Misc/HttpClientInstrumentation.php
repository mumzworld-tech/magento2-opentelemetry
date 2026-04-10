<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Misc;

use GuzzleHttp\Client;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class HttpClientInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.httpClient';
    private const SPAN_NAME_PREFIX = 'HttpClient:';

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
        self::instrumentGuzzleClientSend();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentGuzzleClientSend(): void
    {
        hook(
            Client::class,
            'send',
            static function (
                Client  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $request = $params[0] ?? null;
                $method = $request?->getMethod() ?? 'UNKNOWN';
                $host = $request?->getUri()?->getHost() ?? 'unknown';
                $path = $request?->getUri()?->getPath() ?? '';
                $spanName = sprintf('%s %s %s/%s', self::SPAN_NAME_PREFIX, $method, $host, $path);
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
                Client     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
