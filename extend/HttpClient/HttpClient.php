<?php
/**
 * This file is a part of "furqansiddiqui/http-client" package.
 * https://github.com/furqansiddiqui/http-client
 *
 * Copyright (c) 2018 Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/furqansiddiqui/http-client/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace HttpClient;

use HttpClient\Exception\HttpClientException;

/**
 * Class HttpClient
 */
class HttpClient
{
    const VERSION = "0.3.3";

    /**
     * @param string $url
     * @return Request
     * @throws HttpClientException
     */
    public static function Get(string $url): Request
    {
        return new Request('GET', $url);
    }

    /**
     * @param string $url
     * @return Request
     * @throws HttpClientException
     */
    public static function Post(string $url): Request
    {
        return new Request('POST', $url);
    }

    /**
     * @param string $url
     * @return Request
     * @throws HttpClientException
     */
    public static function Put(string $url): Request
    {
        return new Request('PUT', $url);
    }

    /**
     * @param string $url
     * @return Request
     * @throws HttpClientException
     */
    public static function Delete(string $url): Request
    {
        return new Request('DELETE', $url);
    }

    /**
     * Prerequisites Check
     * @return bool
     * @throws HttpClientException
     */
    public static function Test(): bool
    {
        // Curl
        if (!extension_loaded("curl")) {
            throw new HttpClientException('Required extension "curl" is unavailable');
        }

        // Json
        if (!function_exists("json_encode")) {
            throw new HttpClientException('Required extension "json" is unavailable');
        }

        return true;
    }
}