<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Core;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Http;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class MagentoInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento';

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
        self::instrumentBootstrapRun();
        self::instrumentAppTerminate();
        self::instrumentHttpCatchException();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function instrumentBootstrapRun(): void
    {
        hook(
            Bootstrap::class,
            'run',
            static function (
                Bootstrap   $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $spanName = 'Bootstrap::run()';
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setSpanKind(SpanKind::KIND_INTERNAL);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Bootstrap   $subject,
                array       $params,
                mixed       $returnValue,
                ?Throwable  $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentAppTerminate(): void
    {
        hook(
            Bootstrap::class,
            'terminate',
            static function (
                Bootstrap $bootstrap,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno
            ) {
                $scope = Context::storage()->scope();
                $span = Span::getCurrent();
                $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, "500");
                $span->recordException($params[0]);
                $span->setStatus(StatusCode::STATUS_ERROR, $params[0]->getMessage());
                $scope->detach();
                $span->end();
            }
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentHttpCatchException(): void
    {

        hook(
            Http::class,
            'catchException',
            null,
            static function (
                Http $subject,
                array $params,
                mixed $returnValue,
                ?Throwable $exception,
            ) {
                $httpException = $params[1] ?? $exception;
                self::endSpan($httpException);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
