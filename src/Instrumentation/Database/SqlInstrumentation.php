<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Database;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Adapter\Pdo\Mysql\Interceptor as MysqlInterceptor;
use Magento\Framework\DB\Select;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Util\SpanLimiter;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class SqlInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.sql';
    private const SPAN_NAME_PREFIX = 'SQL:';
    private const SQL_QUERY_SPAN_LIMIT = 2048;

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
        self::instrumentSqlQuery();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentSqlQuery(): void
    {
        $limiter = SpanLimiter::getInstance();
        $limiter->setLimits(['default' => self::SQL_QUERY_SPAN_LIMIT]);
        hook(
            Mysql::class,
            'query',
            static function (
                Mysql $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) use ($limiter) {
                $resolverClass = get_class($subject);
                if (!$limiter->shouldStart($resolverClass)) {
                    return;
                }

                [$sqlString, ] = self::prepareQueryData($params);
                $spanName = sprintf(
                    '%s %s',
                    self::SPAN_NAME_PREFIX,
                    self::getOperationNameFromQuery($sqlString)
                );
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(
                        TraceAttributes::DB_QUERY_TEXT,
                        self::saferStringEncode(self::truncateString($sqlString))
                    )
                    // Note: Uncomment only if required. It might expose sensitive data.
                    // Don't forget to add back the $bind var: [$sqlString, $bind] = self::prepareQueryData($params);
                    /*->setAttribute(
                        TraceAttributes::DB_OPERATION_PARAMETER,
                        self::saferStringEncode(self::saferJsonEncode(self::extractElements($bind)))
                    )*/;
                self::startSpanAndAttachToContext($builder);
            },
            static function (
                Mysql       $subject,
                array       $params,
                mixed       $returnValue,
                ?Throwable  $exception,
            ) use ($limiter) {
                $resolverClass = get_class($subject);
                if ($limiter->wasSkipped($resolverClass)) {
                    return;
                }

                self::updateSpanWithRowCount($returnValue);
                self::endSpan($exception);

                $limiter->clear($resolverClass);
            },
        );
    }

    /**
     * @param array $params
     * @return array
     */
    private static function prepareQueryData(array $params): array
    {
        $sql = $params[0] ?? '';
        $bind = $params[1] ?? [];

        if ($sql instanceof Select) {
            $bind = empty($bind) ? $sql->getBind() : $bind;
            $sqlString = $sql->__toString();
        } else {
            $bind = is_array($bind) ? $bind : [$bind];
            $sqlString = $sql;
        }

        return [(string)$sqlString, $bind];
    }

    /**
     * @param mixed $returnValue
     * @return void
     */
    private static function updateSpanWithRowCount(mixed $returnValue): void
    {
        if (!$returnValue || !is_object($returnValue) || !method_exists($returnValue, 'rowCount')) {
            return;
        }

        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }

        $span = Span::fromContext($scope->context());
        if (!$span) {
            return;
        }

        $span->setAttribute('db.query.affected_rows', $returnValue->rowCount());
    }

    /**
     * Gets operation name from the SQL query
     *
     * @param string $query
     * @return string
     */
    public static function getOperationNameFromQuery(string $query): string
    {
        $pattern = '/^\s*('
            . 'SELECT|INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|'
            . 'EXEC|DESCRIBE|SHOW|SET|USE|BEGIN|COMMIT|ROLLBACK|EXPLAIN'
            . ')\b/i';

        if (preg_match($pattern, $query, $matches)) {
            return strtoupper($matches[1]);
        }

        return 'UNKNOWN';
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
