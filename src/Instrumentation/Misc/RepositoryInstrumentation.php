<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Misc;

use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Quote\Model\QuoteRepository;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RepositoryInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.repository';
    private const SPAN_NAME_PREFIX = 'Repository:';

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
        self::instrumentProductRepositoryGet();
        self::instrumentProductRepositoryGetById();
        self::instrumentCategoryRepositoryGet();
        self::instrumentQuoteRepositoryGet();
        self::instrumentQuoteRepositoryGetActive();
        self::instrumentCustomerRepositoryGet();
        self::instrumentCustomerRepositoryGetById();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentProductRepositoryGet(): void
    {
        hook(
            ProductRepository::class,
            'get',
            static function (
                ProductRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s ProductRepository::get', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setAttribute('magento.misc.repository.sku', $params[0] ?? null)
                ;

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                ProductRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentProductRepositoryGetById(): void
    {
        hook(
            ProductRepository::class,
            'getById',
            static function (
                ProductRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s ProductRepository::getById', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                ->setAttribute('magento.misc.repository.id', $params[0] ?? null);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                ProductRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentCategoryRepositoryGet(): void
    {
        hook(
            CategoryRepository::class,
            'get',
            static function (
                CategoryRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s CategoryRepository::get', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                ->setAttribute('magento.misc.repository.id', $params[0] ?? null);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                CategoryRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentQuoteRepositoryGet(): void
    {
        hook(
            QuoteRepository::class,
            'get',
            static function (
                QuoteRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s QuoteRepository::get', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                ->setAttribute('magento.misc.repository.id', $params[0] ?? null);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                QuoteRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentQuoteRepositoryGetActive(): void
    {
        hook(
            QuoteRepository::class,
            'getActive',
            static function (
                QuoteRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s QuoteRepository::getActive', self::SPAN_NAME_PREFIX);
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
                QuoteRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentCustomerRepositoryGetById(): void
    {
        hook(
            CustomerRepository::class,
            'getById',
            static function (
                CustomerRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s CustomerRepository::getById', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setAttribute('magento.misc.repository.id', $params[0] ?? null);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                CustomerRepository     $subject,
                array      $params,
                $returnValue,
                ?Throwable $exception,
            ) {
                self::endSpan($exception);
            },
        );
    }

    private static function instrumentCustomerRepositoryGet(): void
    {
        hook(
            CustomerRepository::class,
            'get',
            static function (
                CustomerRepository  $subject,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $spanName = sprintf('%s CustomerRepository::get', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setAttribute('magento.misc.repository.email', $params[0] ?? null);

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                CustomerRepository     $subject,
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
