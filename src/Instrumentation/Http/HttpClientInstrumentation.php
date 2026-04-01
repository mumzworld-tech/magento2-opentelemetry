<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Http;

use GuzzleHttp\Client;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class HttpClientInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.http.client';
    private const SPAN_NAME_PREFIX = 'External:';

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
                if ($request
                    && is_object($request)
                    && method_exists($request, 'getMethod')
                    && method_exists($request, 'getUri')
                ) {
                    $uri = $request->getUri();
                    $host = (is_object($uri) && method_exists($uri, 'getHost')) ? $uri->getHost() : '';
                    $path = (is_object($uri) && method_exists($uri, 'getPath')) ? $uri->getPath() : '';
                    $spanName = sprintf(
                        '%s %s %s/%s',
                        self::SPAN_NAME_PREFIX,
                        $request->getMethod(),
                        $host,
                        $path
                    );
                } else {
                    $spanName = sprintf('%s %s', self::SPAN_NAME_PREFIX, 'NA');
                }

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
                mixed      $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
