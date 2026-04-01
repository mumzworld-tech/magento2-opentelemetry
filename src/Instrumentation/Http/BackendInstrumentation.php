<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Http;

use Magento\Backend\App\AbstractAction as BackendAbstractAction;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Mumzworld\OpenTelemetry\Instrumentation\AbstractInstrumentation;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\RequestHandler;
use Mumzworld\OpenTelemetry\Instrumentation\Util\Http\ResponseHandler;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class BackendInstrumentation extends AbstractInstrumentation
{
    public const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.magento.backend';
    private const SPAN_NAME_PREFIX = 'Backend:';

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
        self::instrumentBackendDispatch();
    }

    /**
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private static function instrumentBackendDispatch(): void
    {
        hook(
            BackendAbstractAction::class,
            'dispatch',
            static function (
                BackendAbstractAction        $subject,
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
                    $lineno
                )->setSpanKind(SpanKind::KIND_SERVER);

                if ($request instanceof RequestInterface) {
                    $rootSpanName = RequestHandler::generateRootSpanName(self::SPAN_NAME_PREFIX, $request);

                    // Special handling in case of /mui/index/render
                    if ($namespace = RequestHandler::extractNamespace($request)) {
                        $rootSpanName .= sprintf(' (%s)', $namespace);
                        $builder->setAttribute('http.request.mui.query.namespace', $namespace);
                    }
                    self::updateRootSpanName($rootSpanName);
                    $builder = RequestHandler::addRequestAttributesToSpan($builder, $request);
                }

                self::startSpanAndAttachToContext($builder);
            },
            static function (
                BackendAbstractAction $subject,
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
    //phpcs:enable Magento2.Functions.StaticFunction
}
