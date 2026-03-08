<?php

declare(strict_types=1);

namespace Seventhings\Objects;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\ListOptions;
use Seventhings\Response;

final class ObjectsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?ListOptions $options = null): array
    {
        return $this->httpClient->get('objects', $options)->json()['items'];
    }

    public function count(?ListOptions $options = null): int
    {
        return $this->httpClient->get('objects/count', $options)->json()['count'];
    }

    public function create(array $fields): string
    {
        $response = $this->httpClient->post('object', $fields);

        return Helpers::uuidFromLocationHeader($response);
    }

    public function get(string $uuid): array
    {
        return $this->httpClient->get('object/' . $uuid)->json();
    }

    public function patch(string $uuid, array $fields): void
    {
        $this->httpClient->patch('object/' . $uuid, $fields);
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('object/' . $uuid);
    }

    public function archive(string $uuid): void
    {
        $this->httpClient->post('object/' . $uuid . '/archive');
    }

    public function unarchive(string $uuid): void
    {
        $this->httpClient->post('object/' . $uuid . '/unarchive');
    }

    /**
     * @param \Seventhings\Models\FileAttachment[] $attachments
     */
    public function addFiles(string $uuid, array $attachments): Response
    {
        return $this->httpClient->post(
            'object/' . $uuid . '/add-file',
            array_map(fn($a) => $a->toArray(), $attachments),
        );
    }

    /**
     * @param \Seventhings\Models\FileAttachment[] $attachments
     */
    public function removeFiles(string $uuid, array $attachments): Response
    {
        return $this->httpClient->post(
            'object/' . $uuid . '/remove-file',
            array_map(fn($a) => $a->toArray(), $attachments),
        );
    }
}
