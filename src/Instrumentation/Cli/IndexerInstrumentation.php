<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Cli;

use Magento\Framework\Mview\ActionInterface;
use Magento\Framework\Mview\View;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Indexer\Model\Processor;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class IndexerInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.indexer';
    private const SPAN_NAME_PREFIX = 'Indexer:';

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
        self::instrumentReindexAllInvalid();
        self::instrumentMviewExecute();
        self::instrumentActionExecute();
        self::instrumentClearChangeLog();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentReindexAllInvalid(): void
    {
        hook(
            Processor::class,
            'reindexAllInvalid',
            static function (
                Processor   $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $spanName = sprintf('%s ReindexAllInvalid', self::SPAN_NAME_PREFIX);
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
                Processor   $subject,
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
    private static function instrumentMviewExecute(): void
    {
        hook(
            View::class,
            'executeAction',
            static function (
                View        $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $action = $params[0];
                $lastVersionId = $params[1];
                $currentVersionId = $params[2];

                $spanName = sprintf(
                    '%s %s::executeAction()',
                    self::SPAN_NAME_PREFIX,
                    self::extractClassName(get_class($action))
                );
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setAttribute('magento.cli.indexer.actionInterface', get_class($action))
                    ->setAttribute('magento.cli.indexer.lastVersionId', $lastVersionId)
                    ->setAttribute('magento.cli.indexer.currentVersionId', $currentVersionId);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                View        $subject,
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
    private static function instrumentActionExecute(): void
    {
        hook(
            ActionInterface::class,
            'execute',
            static function (
                ActionInterface        $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $ids = $params[0] ?? [];

                $spanName = sprintf(
                    '%s %s execute',
                    self::SPAN_NAME_PREFIX,
                    self::extractClassName($class)
                );
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setAttribute('magento.cli.indexer.ids', implode(',', self::extractElements($ids)));

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                ActionInterface        $subject,
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
    private static function instrumentClearChangeLog(): void
    {
        hook(
            ChangelogInterface::class,
            'clear',
            static function (
                ChangelogInterface        $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $versionId = $params[0] ?? 'NA';

                $spanName = sprintf('%s CleanChangelog', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute('magento.cli.indexer.changelog.clear.versionId', $versionId);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                ChangelogInterface        $subject,
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
