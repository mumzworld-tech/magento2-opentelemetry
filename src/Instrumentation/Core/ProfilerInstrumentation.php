<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 * NOTE: Should be used only for Development and Testing purposes.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Core;

use Magento\Framework\Profiler;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use function OpenTelemetry\Instrumentation\hook;

class ProfilerInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.profiler';
    private const SPAN_NAME_PREFIX = 'Profiler:';

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
        self::instrumentProfilerStart();
        self::instrumentProfilerStop();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function instrumentProfilerStart(): void
    {
        hook(
            Profiler::class,
            'start',
            static function (
                $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $profilerName = $params[0] ?? 'NA';
                $profilerTags = $params[1] ?? [];
                $spanName = sprintf('%s start(%s)', self::SPAN_NAME_PREFIX, $profilerName);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setAttribute('magento.profiler.tag', self::saferJsonEncode($profilerTags));

                $parent = Context::getCurrent();
                $span = $builder->setParent($parent)->startSpan();
                $spanScope = $span->activate();

                // We are using only pre-hooks so ending span here
                $spanScope->detach();
                $span->end();
            },
            null
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function instrumentProfilerStop(): void
    {
        hook(
            Profiler::class,
            'stop',
            static function (
                $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $profilerName = $params[0] ?? 'NA';
                $spanName = sprintf('%s stop(%s)', self::SPAN_NAME_PREFIX, $profilerName);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setSpanKind(SpanKind::KIND_INTERNAL);

                $parent = Context::getCurrent();
                $span = $builder->setParent($parent)->startSpan();
                $spanScope = $span->activate();
                // We are using only pre-hooks so ending span here
                $spanScope->detach();
                $span->end();
            },
            null
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
