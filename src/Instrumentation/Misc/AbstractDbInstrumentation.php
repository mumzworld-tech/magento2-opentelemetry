<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Misc;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class AbstractDbInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.abstractDb';
    private const SPAN_NAME_PREFIX = 'AbstractDb:';

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
        self::instrumentDbLoad();
        self::instrumentDbSave();
        self::instrumentDbDelete();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentDbLoad(): void
    {
        hook(
            AbstractDb::class,
            'load',
            static function (
                AbstractDb  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $object = $params[0];
                //$spanName = sprintf('%s: %s', 'DbLoad', self::extractClassName(get_class($object)));
                $spanName = sprintf('%s load: %s', self::SPAN_NAME_PREFIX, $subject->getMainTable());
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
                AbstractDb     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentDbSave(): void
    {
        hook(
            AbstractDb::class,
            'save',
            static function (
                AbstractDb  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $object = $params[0];
                //$spanName = sprintf('%s: %s', 'DbSave',  self::extractClassName(get_class($object)));
                $spanName = sprintf('%s save: %s', self::SPAN_NAME_PREFIX, $subject->getMainTable());
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
                AbstractDb     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentDbDelete(): void
    {
        hook(
            AbstractDb::class,
            'delete',
            static function (
                AbstractDb  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $object = $params[0];
                //$spanName = sprintf('%s: %s', 'DbDelete',  self::extractClassName(get_class($object)));
                $spanName = sprintf('%s delete: %s', self::SPAN_NAME_PREFIX, $subject->getMainTable());
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
                AbstractDb     $subject,
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
