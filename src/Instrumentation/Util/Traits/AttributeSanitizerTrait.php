<?php
/**
 * This file is part of the Mumzworld_OpenTelemetry package.
 *
 * @author    Raj KB <rajendra.bhatta@mumzworld.com>
 * @copyright Copyright (c) 2025 MumzWorld (https://www.mumzworld.com)
 */
declare(strict_types=1);

namespace Mumzworld\OpenTelemetry\Instrumentation\Util\Traits;

use Exception;

trait AttributeSanitizerTrait
{
    /**
     * Truncates a string to a specified length and appends an ellipsis if needed.
     *
     * @param string $string  The input string.
     * @param int    $length  The maximum length of the string.
     * @param string $append  The string to append if truncation occurs.
     *
     * @return string The truncated string.
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function truncateString(string $string, int $length = 2048, string $append = '...'): string
    {
        $string = trim($string);
        if (strlen($string) <= $length) {
            return $string;
        }

        $truncated = substr($string, 0, $length);
        return $truncated . $append;
    }

    /**
     * Extracts a subset of elements from an array.
     *
     * @param array $array        The input array.
     * @param int   $n            The number of elements to extract.
     * @param int   $offset       The starting position in the array.
     * @param bool  $preserveKeys Whether to preserve keys.
     *
     * @return array The extracted elements.
     */
    public static function extractElements(
        array $array,
        int $n = 50,
        int $offset = 0,
        bool $preserveKeys = false
    ): array {
        if (empty($array)) {
            return [];
        }
        $n = max(0, $n);
        $offset = max(0, min(count($array) - 1, $offset));
        return array_slice($array, $offset, $n, $preserveKeys);
    }

    /**
     * Safely encodes data into a JSON string.
     * If encoding fails, it returns an empty JSON object.
     *
     * @param mixed $data The data to encode.
     *
     * @return string The JSON-encoded string or '{}' on failure.
     */
    public static function saferJsonEncode(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            // Log the error if necessary
            return '{}';
        }
    }

    /**
     * Safely decodes JSON string data into an array
     * If decoding fails, it returns an empty []
     *
     * @param string $data
     *
     * @return array
     */
    public static function saferJsonDecode(string $data): array
    {
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Safely convert data to a UTF-8 encoded string for a span attribute.
     *
     * @param mixed $data The data to convert (array, object, or primitive).
     * @return string The safely encoded UTF-8 string.
     */
    public static function saferStringEncode(mixed $data): string
    {
        try {
            if (is_array($data)) {
                $data = implode(', ', $data);
            } elseif (is_object($data)) {
                if (method_exists($data, '__toString')) {
                    $data = (string)$data;
                } else {
                    // Fallback: convert object to JSON.
                    $data = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
                }
            } elseif ($data === null) {
                $data = '';
            }

            // Ensure the data is a string.
            if (!is_string($data)) {
                $data = (string)$data;
            }

            return mb_convert_encoding($data, 'UTF-8');
        } catch (Exception $e) {
            // Log the error if necessary

            return '';
        }
    }
    //phpcs:enable Magento2.Functions.StaticFunction
}
