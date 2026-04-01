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
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Webapi\Controller\Rest\Interceptor as RestInterceptor;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\RequestHandler;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\ResponseHandler;
use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RestInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.rest';
    private const SPAN_NAME_PREFIX = 'Rest:';

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
        self::instrumentRestDispatch();
        self::instrumentWebapiException();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentRestDispatch(): void
    {
        hook(
            RestInterceptor::class,
            'dispatch',
            static function (
                RestInterceptor        $subject,
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
                    self::updateRootSpanName(
                        RequestHandler::generateRootSpanName(self::SPAN_NAME_PREFIX, $request, 4)
                    );
                    $builder->setAttribute(
                        'http.request.rest.body',
                        self::truncateString(
                            RequestHandler::maskSensitivePayload(
                                (string) $request->getContent()
                            )
                        )
                    );

                    $builder = RequestHandler::addRequestAttributesToSpan($builder, $request);
                }

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                RestInterceptor $subject,
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
    private static function instrumentWebapiException(): void
    {
        hook(
            WebapiException::class,
            '__construct',
            static function (
                WebapiException        $subject,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $spanName = sprintf('%s Exception', self::SPAN_NAME_PREFIX);
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
                WebapiException $subject,
                array $params,
                mixed $returnValue,
                ?Throwable $exception,
            ) {
                $statusCode = $subject->getHttpCode();
                if ($statusCode >= 400 && $statusCode < 600) {
                    if ($rootSpan = LocalRootSpan::current()) {
                        $rootSpan->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $statusCode);
                        $rootSpan->setStatus(StatusCode::STATUS_ERROR);
                    }
                }
                self::endSpan($subject);
            },
        );
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
