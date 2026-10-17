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

    /**
     * Downloads the default thumbnail, or one fitted within $width x $height
     * (aspect ratio preserved) when both are given. Dimensions must be between
     * 1 and 2000 pixels. The API creates and caches new sizes and may reject
     * excess sizes with 422.
     */
    public function getThumbnail(string $uuid, ?int $width = null, ?int $height = null): string
    {
        $path = 'file/' . $uuid . '/thumbnail';
        if ($width !== null || $height !== null) {
            if ($width === null || $height === null || $width < 1 || $width > 2000 || $height < 1 || $height > 2000) {
                throw new \InvalidArgumentException('thumbnail width and height must both be between 1 and 2000');
            }
            $path .= '?width=' . $width . '&height=' . $height;
        }

        return $this->httpClient->getRaw($path)->body;
    }
}
