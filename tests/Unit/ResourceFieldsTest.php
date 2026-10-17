<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Locations\LocationsService;
use Seventhings\Rooms\RoomsService;

final class ResourceFieldsTest extends TestCase
{
    public static function responseFormats(): iterable
    {
        foreach ([RoomsService::class, LocationsService::class] as $service) {
            foreach (['list', 'get', 'patch', 'all'] as $operation) {
                foreach (['flat', 'wrapped'] as $format) {
                    yield "$service/$operation/$format" => [$service, $operation, $format];
                }
            }
        }
    }

    #[Test]
    #[DataProvider('responseFormats')]
    public function returnsResponseBodyUnchanged(string $serviceClass, string $operation, string $format): void
    {
        $fields = ['id' => 42, 'name' => 'Office', 'cost_center' => 'CC-1', 'picture' => [['uuid' => 'file-1']]];
        $expected = $format === 'wrapped' ? ['uuid' => 'resource-1', 'fields' => $fields] : $fields + ['uuid' => 'resource-1'];
        $payload = $expected;
        if ($operation === 'list' || $operation === 'all') {
            $payload = ['items' => [$payload]];
        }
        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(200, [], json_encode($payload))]));
        $service = new $serviceClass(new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack])));

        $result = match ($operation) {
            'list' => $service->list()[0],
            'get' => $service->get('resource-1'),
            'patch' => $service->patch('resource-1', ['name' => 'Office']),
            'all' => iterator_to_array($service->all())[0]->data,
        };
        $this->assertSame($expected, $result);
    }
}
