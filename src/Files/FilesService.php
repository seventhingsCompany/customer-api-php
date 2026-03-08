<?php

declare(strict_types=1);

namespace Seventhings\Files;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\FileResponse;

final class FilesService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /**
     * @return FileResponse[]
     */
    public function list(): array
    {
        $response = $this->httpClient->get('files');

        return array_map(
            fn(array $item) => FileResponse::fromArray($item),
            $response->json()['items'],
        );
    }

    public function get(string $uuid): FileResponse
    {
        $response = $this->httpClient->get('file/' . $uuid);

        return FileResponse::fromArray($response->json());
    }

    /**
     * @param resource|string $stream
     */
    public function upload(string $filename, mixed $stream): string
    {
        $response = $this->httpClient->postMultipart('file', [
            [
                'name' => 'data',
                'contents' => $stream,
                'filename' => $filename,
            ],
        ]);

        return Helpers::uuidFromFileUpload($response);
    }

    public function getData(string $uuid): string
    {
        return $this->httpClient->getRaw('file/' . $uuid . '/data')->body;
    }

    public function getThumbnail(string $uuid): string
    {
        return $this->httpClient->getRaw('file/' . $uuid . '/thumbnail')->body;
    }
}
