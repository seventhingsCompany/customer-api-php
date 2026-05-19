<?php

declare(strict_types=1);

namespace Seventhings;

use Seventhings\Auth\AuthService;
use Seventhings\CircularityHub\CircularityHubService;
use Seventhings\FieldDefinitions\FieldDefinitionsService;
use Seventhings\Files\FilesService;
use Seventhings\Locations\LocationsService;
use Seventhings\Objects\ObjectsService;
use Seventhings\Persons\PersonsService;
use Seventhings\Rentals\RentalsService;
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
    public readonly FieldDefinitionsService $fieldDefinitions;
    public readonly CircularityHubService $circularityHub;

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
        $this->fieldDefinitions = new FieldDefinitionsService($httpClient);
        $this->circularityHub = new CircularityHubService($httpClient);
    }

    public static function withToken(string $baseUrl, string $token): self
    {
        $httpClient = new HttpClient($baseUrl);
        $httpClient->setToken($token);
        return new self($httpClient);
    }

    public static function withCredentials(string $baseUrl, string $username, string $password, string $clientId): self
    {
        $httpClient = new HttpClient($baseUrl);
        $client = new self($httpClient);
        $tokenResponse = $client->auth->login($username, $password, $clientId);
        $httpClient->setToken($tokenResponse->accessToken);
        return $client;
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
