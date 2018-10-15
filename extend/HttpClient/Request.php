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
use HttpClient\Exception\RequestException;
use HttpClient\Exception\ResponseException;
use HttpClient\Response\HttpClientResponse;
use HttpClient\Response\JSONResponse;
use HttpClient\Response\Response;

/**
 * Class Request
 * @package HttpClient
 */
class Request
{
    /** @var string */
    private $method;
    /** @var string */
    private $url;
    /** @var array */
    private $headers;
    /** @var null|array */
    private $payload;
    /** @var bool */
    private $json;
    /** @var null|SSL */
    private $ssl;
    /** @var null|Authentication */
    private $auth;


    /**
     * Request constructor.
     * @param string $method
     * @param string $url
     * @throws HttpClientException
     */
    public function __construct(string $method, string $url)
    {
        // Check request method
        $method = strtoupper($method);
        if (!in_array($method, ["GET", "POST", "PUT", "DELETE"])) {
            throw new HttpClientException(
                sprintf('"%s" is not a valid or unsupported HTTP request method', $method)
            );
        }

        $this->method = $method;
        $this->headers = [];
        $this->json = false;
        $this->url($url);
    }

    /**
     * @param string $url
     * @return Request
     * @throws HttpClientException
     */
    public function url(string $url): self
    {
        if (!preg_match('/^(http|https):\/\/.*$/i', $url)) {
            throw new HttpClientException('Invalid URL');
        }

        $this->url = $url;
        return $this;
    }

    /**
     * @return SSL
     * @throws Exception\SSLException
     */
    public function ssl(): SSL
    {
        if (!$this->ssl) {
            $this->ssl = new SSL();
        }

        return $this->ssl;
    }

    /**
     * @return Authentication
     */
    public function authentication(): Authentication
    {
        if (!$this->auth) {
            $this->auth = new Authentication();
        }

        return $this->auth;
    }

    /**
     * @param array $data
     * @return Request
     */
    public function payload(array $data): self
    {
        $this->payload = $data;
        return $this;
    }

    /**
     * @param string $header
     * @param string $value
     * @return Request
     */
    public function header(string $header, string $value): self
    {
        $this->headers[] = sprintf('%s: %s', $header, $value);
        return $this;
    }

    /**
     * @return Request
     */
    public function json(): self
    {
        $this->json = true;
        return $this;
    }

    /**
     * @return HttpClientResponse
     * @throws HttpClientException
     * @throws RequestException
     * @throws ResponseException
     */
    public function send(): HttpClientResponse
    {
        HttpClient::Test(); // Prerequisites check

        $ch = curl_init(); // Init cURL handler
        curl_setopt($ch, CURLOPT_URL, $this->url); // Set URL

        // SSL?
        if (strtolower(substr($this->url, 0, 5)) === "https") {
            call_user_func([$this->ssl(), "register"], $ch); // Register SSL options
        }

        // Payload
        switch ($this->method) {
            case "GET":
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
                if ($this->payload) {
                    if ($this->json) {
                        $payload = json_encode($this->payload);
                        if (!$payload) {
                            throw new RequestException('Failed to JSON encode the payload');
                        }

                        $this->header('Content-type', 'application/json; charset=utf-8');
                        $this->header('Content-length', strval(strlen($payload)));
                    } else {
                        $payload = http_build_query($this->payload);
                    }

                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                }
                break;
        }

        // Headers
        if ($this->headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }

        // Authentication
        if ($this->auth) {
            call_user_func([$this->auth, "register"], $ch);
        }

        // Finalise request
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function (
            /** @noinspection PhpUnusedParameterInspection */
            $ch, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        });

        // Execute cURL request
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RequestException(
                sprintf('cURL error [%d]: %s', curl_error($ch), curl_error($ch))
            );
        }

        // Response code
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (is_string($responseCode) && preg_match('/[0-9]+/', $responseCode)) {
            $responseCode = intval($responseCode); // In case HTTP response code is returned as string
        }

        if (!is_int($responseCode)) {
            throw new RequestException('Could not retrieve HTTP response code');
        }

        // Close cURL resource
        curl_close($ch);

        // Prepare response
        $jsonResponse = is_string($responseType) && preg_match('/json/', $responseType) ? true : $this->json;
        if ($jsonResponse) {
            if (!is_string($responseType)) {
                throw new ResponseException('Invalid "Content-type" header received, expecting JSON', $responseCode);
            }

            if (strtolower(trim(explode(";", $responseType)[0])) !== "application/json") {
                throw new ResponseException(
                    sprintf('Expected "application/json", got "%s"', $responseType),
                    $responseCode
                );
            }

            return new JSONResponse($responseCode, $responseHeaders, $response); // Return
        }

        return new Response($responseCode, $responseHeaders, $response); // Return
    }
}