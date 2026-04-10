<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util\Http;

use Exception;
use Magento\Framework\App\RequestInterface;

class GraphQlQueryParser
{
    /**
     * Extract GraphQL operation type and name from a request object.
     * Works with both POST requests (JSON body) and GET requests (URL parameters).
     *
     * @param RequestInterface $request
     * @return array{type: string, operation: string|null}
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function parseRequest(RequestInterface $request): array
    {
        $result = [
            'type' => 'Query',
            'operation' => null
        ];

        try {
            // First try to get data from request body (POST)
            $content = $request->getContent();
            if (!empty($content)) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                    return self::parseJsonData($data);
                }
            }

            // If we got here, try to get from URL (GET)
            $url = (string)$request->getUri();

            return self::parseUrl($url);
        } catch (Exception) {

            return $result;
        }
    }

    /**
     * Parse decoded JSON data from a GraphQL request
     *
     * @param array $data
     * @return array{type: string, operation: string|null}
     */
    public static function parseJsonData(array $data): array
    {
        $result = [
            'type' => 'Query',
            'operation' => null
        ];

        if (!isset($data['query'])) {
            return $result;
        }

        // Check if operationName is provided in the JSON
        if (!empty($data['operationName'])) {
            $result['operation'] = (string)$data['operationName'];
        }

        $queryString = trim($data['query']);

        // Extract operation type (Query or Mutation)
        if (preg_match('/^(query|mutation)/i', $queryString, $typeMatches)) {
            $result['type'] = ucfirst(strtolower($typeMatches[1]));
        }

        // If there's no operation name from JSON, try to extract it from the query
        if ($result['operation'] === null) {
            $result['operation'] = self::extractOperationNameFromQuery($queryString);
        }

        return $result;
    }

    /**
     * Parse a GraphQL URL to extract query type and operation name.
     *
     * @param string $url The URL containing GraphQL parameters
     * @return array{type: string, operation: string|null} Associative array with 'type' and 'operation' keys
     */
    public static function parseUrl(string $url): array
    {
        // Default values
        $result = [
            'type' => 'Query', // Default to Query if not found
            'operation' => null
        ];

        // Parse URL to get query parameters
        $queryParams = [];
        //phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['query'])) {
            //phpcs:ignore Magento2.Functions.DiscouragedFunction
            parse_str($parsedUrl['query'], $queryParams);
        } else {
            return $result; // No query parameters found
        }

        // Check if operationName is provided in the URL
        if (!empty($queryParams['operationName'])) {
            $result['operation'] = $queryParams['operationName'];
        }

        // If query string exists, try to determine the operation type
        if (!empty($queryParams['query'])) {
            $queryString = urldecode($queryParams['query']);

            // Extract operation type (Query or Mutation)
            if (preg_match('/^(query|mutation)/i', $queryString, $typeMatches)) {
                $result['type'] = ucfirst(strtolower($typeMatches[1]));
            }

            // If no operation name was found yet, try to extract it from the query string
            if ($result['operation'] === null) {
                $result['operation'] = self::extractOperationNameFromQuery($queryString);
            }
        }

        return $result;
    }

    /**
     * Parse a raw GraphQL query string to extract query type and operation name.
     *
     * @param string $graphqlString
     * @return array{type: string, operation: string|null}
     */
    public static function parseQueryString(string $graphqlString): array
    {
        $result = [
            'type' => 'Query',
            'operation' => null
        ];

        // Trim whitespace
        $graphqlString = trim($graphqlString);

        // Extract operation type (Query or Mutation)
        if (preg_match('/^(query|mutation)\s+/i', $graphqlString, $typeMatches)) {
            $result['type'] = ucfirst(strtolower($typeMatches[1]));
        }

        $result['operation'] = self::extractOperationNameFromQuery($graphqlString);

        return $result;
    }

    /**
     * Extracts operation name from a GraphQL query string
     *
     * @param string $queryString
     * @return string|null
     */
    private static function extractOperationNameFromQuery(string $queryString): ?string
    {
        // Check for named operation after query/mutation keyword
        if (preg_match(
            '/^(?:query|mutation)\s+([A-Za-z][A-Za-z0-9_]*)/i',
            $queryString,
            $nameMatches
        )) {
            return $nameMatches[1];
        } elseif (preg_match('/{\s*([A-Za-z][A-Za-z0-9_]*)/i', $queryString, $matches)) {
            // If not found, look for the first field name inside the outermost curly braces
            return $matches[1];
        }

        return null;
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
