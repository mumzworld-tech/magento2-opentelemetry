<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util\Http;

use Magento\Framework\App\ResponseInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class ResponseHandler
{
    /**
     * Add response attributes to span
     *
     * @param SpanInterface $span
     * @param ResponseInterface $response
     * @return SpanInterface
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function addResponseAttributesToSpan(
        SpanInterface $span,
        ResponseInterface $response
    ): SpanInterface {
        try {
            $statusCode = $response->getStatusCode();
            $span->setStatus($statusCode >= 400 ? StatusCode::STATUS_ERROR : StatusCode::STATUS_OK);
            $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $statusCode);
            $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getVersion());

            if ($xMagentoCacheId = $response->getHeader('X-Magento-Cache-Id')) {
                $span->setAttribute(
                    'http.response.xMagentoCacheId',
                    method_exists($xMagentoCacheId, 'getFieldValue') ? $xMagentoCacheId->getFieldValue() : ''
                );
            }
            if ($xMagentoTags = $response->getHeader('X-Magento-Tags')) {
                $span->setAttribute(
                    'http.response.xMagentoTags',
                    method_exists($xMagentoTags, 'getFieldValue') ? $xMagentoTags->getFieldValue() : ''
                );
            }
            if ($cacheControl = $response->getHeader('Cache-Control')) {
                $span->setAttribute(
                    'http.response.cacheControl',
                    method_exists($cacheControl, 'getFieldValue') ? $cacheControl->getFieldValue() : ''
                );
            }

            return $span;
        } catch (Throwable $e) {
            // Silently handle exceptions to prevent instrumentation from breaking the application

            return $span;
        }
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
