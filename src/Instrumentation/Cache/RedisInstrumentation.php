<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Cache;

use Magento\Framework\Cache\Backend\Redis;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RedisInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.redis';
    private const SPAN_NAME_PREFIX = 'Cache: Redis:';

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
        // Note: only uncomment if required
        #self::instrumentLoad();
        self::instrumentTest();
        self::instrumentSave();
        self::instrumentClean();
        self::instrumentRemove();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentLoad(): void
    {
        hook(
            Redis::class,
            'load',
            static function (
                Redis     $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $cacheId = $params[0] ?? 'NA';
                $spanName = sprintf('%s load', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, TraceAttributeValues::DB_SYSTEM_REDIS)
                    ->setAttribute(TraceAttributes::DB_OPERATION_NAME, 'load')
                    ->setAttribute('db.redis.save.cacheId', $cacheId);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Redis     $subject,
                array       $params,
                mixed       $returnValue,
                ?Throwable  $exception,
            ) {
                // Only uncomment if required. Data might be too big or might have sensitive information
                /*$spanAttributes = [
                    'db.redis.load.cacheData' => self::saferStringEncode($returnValue)
                ];*/
                self::endSpan($exception);
            },
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentTest(): void
    {
        hook(
            Redis::class,
            'test',
            static function (
                Redis       $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $cacheId = $params[0] ?? 'NA';
                $spanName = sprintf('%s test', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, TraceAttributeValues::DB_SYSTEM_REDIS)
                    ->setAttribute(TraceAttributes::DB_OPERATION_NAME, 'test')
                    ->setAttribute('db.redis.save.cacheId', $cacheId);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Redis       $subject,
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
    private static function instrumentSave(): void
    {
        hook(
            Redis::class,
            'save',
            static function (
                Redis       $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                #$cacheData = $params[0] ?? '';
                $cacheId = $params[1] ?? '';
                $cacheTags = $params[2] ?? [];
                $spanName = sprintf('%s save', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                );
                $builder->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, TraceAttributeValues::DB_SYSTEM_REDIS)
                    ->setAttribute(TraceAttributes::DB_OPERATION_NAME, 'save')
                    ->setAttribute('db.redis.save.cacheId', $cacheId)
                    // Note: Only uncomment if required. It might be too big or have sensitive information,
                    // Also can cause errors: OpenTelemetry: [error] Export failure [exception] Bad Request
                    //->setAttribute('db.redis.save.cacheData', self::saferStringEncode($cacheData))
                    ->setAttribute('db.redis.save.cacheTags', implode(',', $cacheTags));

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Redis       $subject,
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
    private static function instrumentRemove(): void
    {
        hook(
            Redis::class,
            'remove',
            static function (
                Redis       $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $cacheId = $params[0] ?? 'NA';
                $spanName = sprintf('%s remove', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, TraceAttributeValues::DB_SYSTEM_REDIS)
                    ->setAttribute(TraceAttributes::DB_OPERATION_NAME, 'remove')
                    ->setAttribute('db.redis.save.cacheId', $cacheId);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Redis       $subject,
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
    private static function instrumentClean(): void
    {
        hook(
            Redis::class,
            'clean',
            static function (
                Redis       $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $mode = $params[0] ?? 'NA';
                $tags = $params[1] ?? [];
                $spanName = sprintf('%s clean', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, TraceAttributeValues::DB_SYSTEM_REDIS)
                    ->setAttribute(TraceAttributes::DB_OPERATION_NAME, 'clean')
                    ->setAttribute('db.redis.clean.mode', $mode)
                    ->setAttribute('db.redis.clean.tags', implode(',', $tags));

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Redis       $subject,
                array       $params,
                mixed       $returnValue,
                ?Throwable  $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
