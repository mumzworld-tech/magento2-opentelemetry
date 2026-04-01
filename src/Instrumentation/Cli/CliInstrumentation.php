<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Cli;

use Magento\Framework\Console\Cli;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Symfony\Component\Console\Command\Command;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class CliInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.cli';
    private const SPAN_NAME_PREFIX = 'Cli:';

    private const SKIPPED_COMMANDS = ['cron:run'];

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
        self::instrumentCliRun();
        self::instrumentCommandRun();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentCliRun(): void
    {
        hook(
            Cli::class,
            'doRun',
            static function (
                Cli     $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s bootstrap', self::SPAN_NAME_PREFIX);
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
                Cli        $subject,
                array      $params,
                mixed      $returnValue,
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
    private static function instrumentCommandRun(): void
    {
        hook(
            Command::class,
            'run',
            static function (
                Command $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $commandName = $subject->getName() ?: 'unknown';
                if (in_array($commandName, self::SKIPPED_COMMANDS)) {
                    return;
                }

                self::updateRootSpanName(sprintf('%s %s', self::SPAN_NAME_PREFIX, $commandName));

                $spanName = sprintf(
                    '%s:run',
                    self::extractClassName($class)
                );
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
                Command    $subject,
                array      $params,
                mixed      $returnValue,
                ?Throwable $exception,
            ) {
                if (in_array($subject->getName(), self::SKIPPED_COMMANDS)) {
                    return;
                }
                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
