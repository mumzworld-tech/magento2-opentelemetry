<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util\Traits;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

trait SpanBuilderTrait
{
    /**
     * Creates and configures a span builder with attributes for tracing.
     *
     * @param string      $spanName  The name of the span.
     * @param string      $function  The function name being traced.
     * @param string      $class     The class name where the function is located.
     * @param string|null $filename  The filename where the function is defined.
     * @param int|null    $lineno    The line number where the function is defined.
     *
     * @return SpanBuilderInterface
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    protected static function createSpanBuilder(
        string $spanName,
        string $function,
        string $class,
        ?string $filename,
        ?int $lineno,
    ): SpanBuilderInterface {
        /** @psalm-suppress ArgumentTypeCoercion */
        return (new CachedInstrumentation(static::getInstrumentationName()))
            ->tracer()
            ->spanBuilder($spanName)
            ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno);
    }

    /**
     * Start a span with the given builder and attach it to the current context
     *
     * @param SpanBuilderInterface $builder The span builder
     * @return SpanInterface The created span
     */
    protected static function startSpanAndAttachToContext(SpanBuilderInterface $builder): SpanInterface
    {
        $parent = Context::getCurrent();
        $span = $builder->setParent($parent)->startSpan();
        Context::storage()->attach($span->storeInContext($parent));

        return $span;
    }

    /**
     * Ends a span and records an exception if provided.
     *
     * @param Throwable|null $exception      Exception to be recorded (if any).
     * @param array          $spanAttributes Additional attributes to set on the span.
     *
     * @return void
     */
    protected static function endSpan(?Throwable $exception, array $spanAttributes = []): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());

        foreach ($spanAttributes as $attribute => $value) {
            $span->setAttribute($attribute, $value);
        }

        if ($exception) {
            $span->recordException($exception, [TraceAttributes::EXCEPTION_MESSAGE => $exception->getMessage()]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, 402);
        }

        $span->end();
    }

    /**
     * Returns the current active span from context.
     *
     * @return SpanInterface|null
     */
    protected static function getActiveSpan(): ?SpanInterface
    {
        $scope = Context::storage()->scope();
        return $scope ? Span::fromContext($scope->context()) : null;
    }

    //phpcs:enable Magento2.Functions.StaticFunction
}
