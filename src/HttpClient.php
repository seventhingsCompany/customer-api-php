<?php

declare(strict_types=1);

namespace Seventhings;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Seventhings\Models\ApiException;
use Seventhings\Models\ListOptions;
use Seventhings\Models\NetworkException;

/**
 * @internal
 */
final class HttpClient
{
    private string $baseUrl;
    private GuzzleClient $guzzle;
    private string $token = '';

    public function __construct(string $baseUrl, ?GuzzleClient $guzzle = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/customer-api/v1';
        $this->guzzle = $guzzle ?? new GuzzleClient();
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function get(string $path, ?ListOptions $options = null): Response
    {
        $url = $this->buildUrl($path);
        if ($options !== null) {
            $qs = $options->toQueryString();
            if ($qs !== '') {
                $url .= '?' . $qs;
            }
        }
        return $this->doAuthenticated('GET', $url);
    }

    public function post(string $path, ?array $body = null): Response
    {
        return $this->doAuthenticated('POST', $this->buildUrl($path), $body);
    }

    public function patch(string $path, ?array $body = null): Response
    {
        return $this->doAuthenticated('PATCH', $this->buildUrl($path), $body);
    }

    public function put(string $path, ?array $body = null): Response
    {
        return $this->doAuthenticated('PUT', $this->buildUrl($path), $body);
    }

    public function delete(string $path): Response
    {
        return $this->doAuthenticated('DELETE', $this->buildUrl($path));
    }

    public function getUnauthenticated(string $path): Response
    {
        return $this->doRequest('GET', $this->buildUrl($path), null, false);
    }

    public function postUnauthenticated(string $path, ?array $body = null): Response
    {
        return $this->doRequest('POST', $this->buildUrl($path), $body, false);
    }

    public function getRaw(string $path): Response
    {
        return $this->doRaw('GET', $this->buildUrl($path));
    }

    public function postMultipart(string $path, array $multipart): Response
    {
        return $this->doMultipart($this->buildUrl($path), $multipart);
    }

    private function buildUrl(string $path): string
    {
        if ($path === '') {
            return $this->baseUrl;
        }
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function doAuthenticated(string $method, string $url, ?array $body = null): Response
    {
        return $this->doRequest($method, $url, $body, true);
    }

    private function doRequest(string $method, string $url, ?array $body, bool $authenticated): Response
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ];

        if ($authenticated && $this->token !== '') {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR);
        }

        return $this->execute($method, $url, $options);
    }

    private function doRaw(string $method, string $url): Response
    {
        $options = [
            'http_errors' => false,
        ];

        if ($this->token !== '') {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        return $this->execute($method, $url, $options);
    }

    private function doMultipart(string $url, array $multipart): Response
    {
        $options = [
            'multipart' => $multipart,
            'http_errors' => false,
        ];

        if ($this->token !== '') {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        return $this->execute('POST', $url, $options);
    }

    private function execute(string $method, string $url, array $options): Response
    {
        try {
            $guzzleResponse = $this->guzzle->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new NetworkException($e->getMessage(), $e);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $resp = $e->getResponse();
                throw new ApiException(
                    $resp->getStatusCode(),
                    (string) $resp->getReasonPhrase(),
                    (string) $resp->getBody(),
                    $e,
                );
            }
            throw new NetworkException($e->getMessage(), $e);
        }

        $statusCode = $guzzleResponse->getStatusCode();
        $body = (string) $guzzleResponse->getBody();

        if ($statusCode >= 400) {
            throw new ApiException(
                $statusCode,
                $guzzleResponse->getReasonPhrase(),
                $body,
            );
        }

        return new Response(
            $statusCode,
            $guzzleResponse->getHeaders(),
            $body,
        );
    }
}
