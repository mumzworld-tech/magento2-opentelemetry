<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Http;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\GraphQl\Controller\GraphQl\Interceptor as GraphQlInterceptor;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\GraphQlQueryParser;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\RequestHandler;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\ResponseHandler;
use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;
use WeakMap;
use function OpenTelemetry\Instrumentation\hook;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GraphQlInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.graphql';
    private const SPAN_NAME_PREFIX = 'GraphQl:';
    private const GRAPHQL_RESOLVER_REPETITION_LIMIT = 3;

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
        self::instrumentGraphQlDispatch();
        self::instrumentQueryProcessor();
        self::instrumentQueryResolver();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function instrumentGraphqlDispatch(): void
    {
        hook(
            GraphQlInterceptor::class,
            'dispatch',
            static function (
                GraphQlInterceptor     $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $request = $params[0] ?? null;
                $spanName = sprintf('%s dispatch', self::SPAN_NAME_PREFIX);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )->setSpanKind(SpanKind::KIND_SERVER);

                if ($request instanceof RequestInterface) {
                    $content = (string) $request->getContent();
                    ['type' => $type, 'operation' => $operation] = GraphQlQueryParser::parseRequest($request);

                    self::updateRootSpanName(
                        sprintf(
                            '%s %s %s',
                            self::SPAN_NAME_PREFIX,
                            $request->getMethod() ?? 'UNKNOWN',
                            $operation ?? 'NA'
                        )
                    );

                    $builder->setAttribute(
                        'http.request.graphql.body',
                        self::truncateString(RequestHandler::maskSensitivePayload($content), 4096)
                    )->setAttribute(
                        'http.request.graphql.operationName',
                        $operation
                    )->setAttribute(
                        'http.request.graphql.operationType',
                        $type
                    );

                    $builder = RequestHandler::addRequestAttributesToSpan($builder, $request);
                }

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                GraphQlInterceptor $subject,
                array $params,
                mixed $response,
                ?Throwable $exception,
            ) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                } else {
                    if (isset($response) && $response instanceof ResponseInterface) {
                        $span = ResponseHandler::addResponseAttributesToSpan($span, $response);
                    }
                }

                $span->end();
            },
        );
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function instrumentQueryProcessor(): void
    {
        hook(
            QueryProcessor::class,
            'process',
            static function (
                QueryProcessor     $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $spanName = sprintf('%s QueryProcessor', self::SPAN_NAME_PREFIX);
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
                QueryProcessor $subject,
                array $params,
                mixed $response,
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
    private static function instrumentQueryResolver(): void
    {
        static $createdSpans;
        if (!isset($createdSpans)) {
            $createdSpans = new WeakMap();
        }
        static $spanCounters = [];

        hook(
            ResolverInterface::class,
            'resolve',
            static function (
                ResolverInterface     $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) use (
                &$spanCounters,
                &$createdSpans
            ) {
                /** @var Field $field */
                $field = $params[0];
                $fieldName = $field->getName();

                // Resolver Span Limiter - If the same resolver is called more than 3 times, don't create a span
                $key = $class . '::' . $fieldName;
                if (($spanCounters[$key] ?? 0) >= self::GRAPHQL_RESOLVER_REPETITION_LIMIT) {
                    $createdSpans[$subject] = false;
                    return;
                }
                $spanCounters[$key] = ($spanCounters[$key] ?? 0) + 1;

                /** @var ContextInterface $context */
                $context = $params[1] ?? null;
                //$info = $params[2] ?? null;
                $value = $params[3] ?? [];
                $args = $params[4] ?? [];

                $spanName = sprintf('%s Resolver: %s', self::SPAN_NAME_PREFIX, $fieldName);
                $builder = self::createSpanBuilder(
                    $spanName,
                    $function,
                    $class,
                    $filename,
                    $lineno,
                )
                    ->setAttribute('http.graphql.resolver.field', $fieldName)
                    ->setAttribute('http.graphql.resolver.context.userId', $context?->getUserId())
                    ->setAttribute('http.graphql.resolver.context.userType', $context?->getUserType())
                    ->setAttribute('http.graphql.resolver.value', self::saferJsonEncode($value))
                    ->setAttribute('http.graphql.resolver.args', RequestHandler::maskSensitivePayload($args));

                self::startSpanAndAttachToContext($builder);

                $createdSpans[$subject] = true;
            },
            static function (
                ResolverInterface $subject,
                array $params,
                mixed $returnValue,
                ?Throwable $exception,
            ) use (&$createdSpans) {
                if (!($createdSpans[$subject] ?? false)) {
                    return;
                }

                // Tweak for the GraphQL response which always returns 200 even if there is an error
                if ($exception) {
                    if ($rootSpan = LocalRootSpan::current()) {
                        $rootSpan->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, 400);
                        $rootSpan->setStatus(StatusCode::STATUS_ERROR);
                    }
                }

                self::endSpan($exception);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
