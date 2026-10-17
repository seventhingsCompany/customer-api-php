<?php

declare(strict_types=1);

namespace Seventhings;

use Seventhings\Auth\AuthService;
use Seventhings\CatalogItems\CatalogItemsService;
use Seventhings\CircularityHub\CircularityHubService;
use Seventhings\FieldDefinitions\FieldDefinitionsService;
use Seventhings\Files\FilesService;
use Seventhings\Locations\LocationsService;
use Seventhings\Objects\ObjectsService;
use Seventhings\Persons\PersonsService;
use Seventhings\Rentals\RentalsService;
use Seventhings\Reports\ReportsService;
use Seventhings\Rooms\RoomsService;
use Seventhings\Tasks\TasksService;
use Seventhings\Users\UsersService;

final class Client
{
    private HttpClient $httpClient;

    public readonly AuthService $auth;
    public readonly ObjectsService $objects;
    public readonly RoomsService $rooms;
    public readonly LocationsService $locations;
    public readonly UsersService $users;
    public readonly PersonsService $persons;
    public readonly FilesService $files;
    public readonly TasksService $tasks;
    public readonly RentalsService $rentals;
    public readonly ReportsService $reports;
    public readonly FieldDefinitionsService $fieldDefinitions;
    public readonly CircularityHubService $circularityHub;
    public readonly CatalogItemsService $catalogItems;

    private function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->auth = new AuthService($httpClient);
        $this->objects = new ObjectsService($httpClient);
        $this->rooms = new RoomsService($httpClient);
        $this->locations = new LocationsService($httpClient);
        $this->users = new UsersService($httpClient);
        $this->persons = new PersonsService($httpClient);
        $this->files = new FilesService($httpClient);
        $this->tasks = new TasksService($httpClient);
        $this->rentals = new RentalsService($httpClient);
        $this->reports = new ReportsService($httpClient);
        $this->fieldDefinitions = new FieldDefinitionsService($httpClient);
        $this->circularityHub = new CircularityHubService($httpClient);
        $this->catalogItems = new CatalogItemsService($httpClient);
    }

    /**
     * Requests through one client are spaced evenly at $requestsPerMinute
     * (default 200, no bursts); zero or less disables throttling. All
     * requests share the limit, including authentication, uploads and
     * downloads. Separate clients do not share a limit, and HTTP 429
     * responses are not retried automatically.
     */
    public static function withToken(
        string $baseUrl,
        string $token,
        int $requestsPerMinute = RateLimiter::DEFAULT_REQUESTS_PER_MINUTE,
    ): self {
        $httpClient = self::createHttpClient($baseUrl, $requestsPerMinute);
        $httpClient->setToken($token);
        return new self($httpClient);
    }

    /** See {@see withToken()} for $requestsPerMinute. */
    public static function withCredentials(
        string $baseUrl,
        string $username,
        string $password,
        string $clientId,
        int $requestsPerMinute = RateLimiter::DEFAULT_REQUESTS_PER_MINUTE,
    ): self {
        $httpClient = self::createHttpClient($baseUrl, $requestsPerMinute);
        $client = new self($httpClient);
        $tokenResponse = $client->auth->login($username, $password, $clientId);
        $httpClient->setToken($tokenResponse->accessToken);
        return $client;
    }

    /**
     * Authenticates by exchanging an OAuth 2.1 authorization code with PKCE.
     * Codes are single-use. See {@see withToken()} for $requestsPerMinute.
     */
    public static function withAuthorizationCode(
        string $baseUrl,
        string $code,
        string $codeVerifier,
        string $redirectUri,
        string $clientId,
        int $requestsPerMinute = RateLimiter::DEFAULT_REQUESTS_PER_MINUTE,
    ): self {
        $httpClient = self::createHttpClient($baseUrl, $requestsPerMinute);
        $client = new self($httpClient);
        $tokenResponse = $client->auth->loginAuthorizationCode($code, $codeVerifier, $redirectUri, $clientId);
        $httpClient->setToken($tokenResponse->accessToken);
        return $client;
    }

    private static function createHttpClient(string $baseUrl, int $requestsPerMinute): HttpClient
    {
        return new HttpClient($baseUrl, null, RateLimiter::perMinute($requestsPerMinute));
    }

    public function setToken(string $token): void
    {
        $this->httpClient->setToken($token);
    }

    /**
     * @internal
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }
}
