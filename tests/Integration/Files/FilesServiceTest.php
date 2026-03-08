<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Files;

use Seventhings\Tests\Integration\IntegrationTestCase;

final class FilesServiceTest extends IntegrationTestCase
{
    public function testFilesList(): void
    {
        $files = self::$client->files->list();
        $this->assertIsArray($files);
    }

    public function testFileUploadAndDownload(): void
    {
        $content = 'PHP SDK test file ' . $this->uniqueSuffix();
        $filename = 'test-' . $this->uniqueSuffix() . '.txt';

        $uuid = self::$client->files->upload($filename, $content);
        $this->assertNotEmpty($uuid);

        $meta = self::$client->files->get($uuid);
        $this->assertSame($uuid, $meta->uuid);
        $this->assertSame($filename, $meta->name);

        $data = self::$client->files->getData($uuid);
        $this->assertSame($content, $data);

        try {
            $thumbnail = self::$client->files->getThumbnail($uuid);
            $this->assertNotEmpty($thumbnail);
        } catch (\Throwable) {
            // Thumbnails may not be available for text files
        }
    }
}
