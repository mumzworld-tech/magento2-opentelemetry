<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util\Http;

use Magento\Framework\App\RequestInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class RequestHandler
{
    /**
     * Add request attributes to span builder
     *
     * @param SpanBuilderInterface $builder
     * @param RequestInterface $request
     * @return SpanBuilderInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function addRequestAttributesToSpan(
        SpanBuilderInterface $builder,
        RequestInterface $request
    ): SpanBuilderInterface {
        try {
            # Standard Headers
            if ($uri = $request->getUri()) {
                $builder->setAttribute(TraceAttributes::URL_FULL, $uri->__toString() ?: '');
                $builder->setAttribute(TraceAttributes::URL_SCHEME, $uri->getScheme() ?: '');
                $builder->setAttribute(TraceAttributes::URL_PATH, $uri->getPath() ?: '');
                $builder->setAttribute(TraceAttributes::SERVER_ADDRESS, $uri->getHost() ?: '');
                $builder->setAttribute(TraceAttributes::SERVER_PORT, $uri->getPort() ?: '');
            }
            $builder->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod() ?: 'UNKNOWN');
            $builder->setAttribute(
                TraceAttributes::HTTP_REQUEST_BODY_SIZE,
                $request->getHeader('Content-Length') ?: ''
            );
            $builder->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeader('User-Agent') ?: '');

            # Magento Specific Headers
            $builder->setAttribute('http.request.store', $request->getHeader('Store') ?: '');
            $builder->setAttribute('http.request.currency', $request->getHeader('Content-Currency') ?: '');
            $builder->setAttribute('http.request.contentType', $request->getHeader('Content-Type') ?: '');
            $builder->setAttribute(
                'http.request.hasAuthorization',
                $request->getHeader('Authorization') ? 'true' : 'false'
            );

            # MumzWorld Specific Headers
            $builder->setAttribute('http.request.xAppId', $request->getHeader('x-app-id') ?: '');

            $traceparent = $request->getHeader('traceparent') ?: '';
            if ($traceInfo = self::extractTraceInfo($traceparent)) {
                $builder->setAttribute('http.request.traceparent', $traceparent);
                $builder->setAttribute('http.request.traceId', $traceInfo['traceId']);
                $builder->setAttribute('http.request.spanId', $traceInfo['spanId']);
            } else {
                $builder->setAttribute('http.request.traceId', $request->getHeader('traceID') ?: '');
            }

            return $builder;
        } catch (Throwable $e) {
            // Silently handle exceptions to prevent instrumentation from breaking the application

            return $builder;
        }
    }

    /**
     * Generate root span name from request
     *
     * @param string $prefix
     * @param RequestInterface $request
     * @param int $noOfUriSegments
     * @return string
     */
    public static function generateRootSpanName(
        string $prefix,
        RequestInterface $request,
        int $noOfUriSegments = 3
    ): string {
        try {
            $path = $request->getUri()?->getPath() ?? '';
            $method = $request->getMethod() ?: 'UNKNOWN';

            return sprintf(
                '%s %s %s',
                $prefix,
                $method,
                self::extractUrlSegments($path, 1, $noOfUriSegments)
            );
        } catch (Throwable $e) {
            return $prefix . ' dispatch';
        }
    }

    /**
     * Extract the `namespace` from the query string if present
     * Esp. in case of /mui/index/render
     *
     * @param RequestInterface $request
     * @return string|null
     */
    public static function extractNamespace(RequestInterface $request): ?string
    {
        try {
            $path = $request->getUri()?->getPath() ?? '';
            if (!str_contains($path, 'mui/index/render')) {
                return null;
            }
            $query = $request->getUri()?->getQuery() ?? '';
            if (empty($query)) {
                return null;
            }
            //phpcs:ignore Magento2.Functions.DiscouragedFunction
            parse_str($query, $queryParams);

            return isset($queryParams['namespace']) && is_string($queryParams['namespace'])
                ? $queryParams['namespace']
                : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Extracts traceId and spanId from a traceparent header string
     * Format: <version>-<traceId>-<spanId>-<flags>
     *
     * @param string $traceparent
     * @return array|null
     */
    private static function extractTraceInfo(string $traceparent): ?array
    {
        $traceparent = trim($traceparent);
        if (empty($traceparent)) {
            return null;
        }

        $traceParts = explode('-', $traceparent);
        // Check if format is valid (should have 4 parts)
        if (count($traceParts) !== 4) {
            return null;
        }

        [, $traceId, $spanId, ] = $traceParts;

        if (!SpanContextValidator::isValidSpanId($spanId) || !SpanContextValidator::isValidTraceId($traceId)) {
            return null;
        }

        return [
            'traceId' => $traceId,
            'spanId' => $spanId
        ];
    }

    /**
     * Extracts specific segments from a URL path.
     * If the path has fewer than 4 segments, it returns the whole path.
     *
     * @param string $url     The URL string.
     * @param int    $offset  The starting segment index.
     * @param int    $length  The number of segments to extract.
     *
     * @return string The extracted segments as a string.
     */
    public static function extractUrlSegments(string $url, int $offset = 1, int $length = 3): string
    {
        //phpcs:ignore Magento2.Functions.DiscouragedFunction
        $path = parse_url($url, PHP_URL_PATH);

        if (empty($path)) {
            return '';
        }

        $segments = explode('/', trim($path, '/'));
        if (count($segments) <= $length) {
            return $path;
        }

        return '/' . implode('/', array_slice($segments, $offset, $length));
    }

    /**
     * Mask sensitive values in a JSON string or array payload.
     * Looks in both the root level and known sub-keys like 'variables' and 'input'.
     *
     * @param string|array $payload JSON string or associative array
     * @param array $sensitiveKeys List of sensitive keys to mask (case-insensitive)
     * @return string Masked JSON string
     */
    public static function maskSensitivePayload(string|array $payload, array $sensitiveKeys = ['password']): string
    {
        try {
            if (empty($payload)) {
                return '';
            }

            $data = is_string($payload)
                ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR)
                : $payload;

            if (!is_array($data)) {
                return is_string($payload) ? $payload : json_encode($payload);
            }

            $sensitiveKeys = array_map('strtolower', $sensitiveKeys);

            self::maskKeysInArray($data, $sensitiveKeys, ['variables', 'input']);

            return json_encode($data, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            return (string) $payload;
        }
    }

    /**
     * Mask sensitive keys in the root and given nested keys if they are arrays.
     *
     * @param array $data The data array to be masked (by reference)
     * @param array $sensitiveKeys List of sensitive keys (lowercased)
     * @param array $nestedKeys Optional nested keys to check (e.g. 'variables', 'input')
     */
    private static function maskKeysInArray(array &$data, array $sensitiveKeys, array $nestedKeys = []): void
    {
        self::applyMask($data, $sensitiveKeys);

        foreach ($nestedKeys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::applyMask($data[$key], $sensitiveKeys);
            }
        }
    }

    /**
     * Apply masking to keys in the given array.
     *
     * @param array $arr Array to apply the mask (by reference)
     * @param array $sensitiveKeys List of sensitive keys (lowercased)
     */
    private static function applyMask(array &$arr, array $sensitiveKeys): void
    {
        foreach ($arr as $key => $value) {
            if (is_string($key) &&
                in_array(strtolower($key), $sensitiveKeys, true) &&
                is_string($value)
            ) {
                $arr[$key] = str_repeat('*', mb_strlen($value));
            }
        }
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
