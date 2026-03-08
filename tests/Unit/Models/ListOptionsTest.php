<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\SortDirection;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\ListOptions;

final class ListOptionsTest extends TestCase
{
    #[Test]
    public function emptyOptionsProducesEmptyString(): void
    {
        $opts = new ListOptions();
        $this->assertSame('', $opts->toQueryString());
    }

    #[Test]
    public function pageOnly(): void
    {
        $opts = new ListOptions(page: 2);
        $this->assertSame('page=2', $opts->toQueryString());
    }

    #[Test]
    public function perPageOnly(): void
    {
        $opts = new ListOptions(perPage: 50);
        $this->assertSame('per_page=50', $opts->toQueryString());
    }

    #[Test]
    public function sortSingleField(): void
    {
        $opts = new ListOptions(sort: ['name' => SortDirection::Asc]);
        $this->assertSame('sort[name]=ASC', $opts->toQueryString());
    }

    #[Test]
    public function singleValueFilter(): void
    {
        $opts = new ListOptions(filters: [
            new FilterEntry('status', FilterOperator::Eq, ['active']),
        ]);
        $this->assertSame('filter[status][eq]=active', $opts->toQueryString());
    }

    #[Test]
    public function multiValueFilter(): void
    {
        $opts = new ListOptions(filters: [
            new FilterEntry('tag', FilterOperator::In, ['a', 'b']),
        ]);
        $this->assertSame('filter[tag][in][]=a&filter[tag][in][]=b', $opts->toQueryString());
    }

    #[Test]
    public function combinedOptions(): void
    {
        $opts = new ListOptions(
            page: 1,
            perPage: 25,
            sort: ['created_at' => SortDirection::Desc],
            filters: [
                new FilterEntry('status', FilterOperator::Eq, ['active']),
            ],
        );
        $this->assertSame(
            'page=1&per_page=25&sort[created_at]=DESC&filter[status][eq]=active',
            $opts->toQueryString(),
        );
    }

    #[Test]
    public function urlEncodesFilterValues(): void
    {
        $opts = new ListOptions(filters: [
            new FilterEntry('name', FilterOperator::Eq, ['hello world&more']),
        ]);
        $this->assertSame('filter[name][eq]=hello%20world%26more', $opts->toQueryString());
    }

    #[Test]
    public function literalBracketsNotEncoded(): void
    {
        $opts = new ListOptions(
            sort: ['name' => SortDirection::Asc],
            filters: [
                new FilterEntry('tag', FilterOperator::In, ['x']),
            ],
        );
        $qs = $opts->toQueryString();
        $this->assertStringContainsString('sort[name]', $qs);
        $this->assertStringContainsString('filter[tag][in][]', $qs);
        $this->assertStringNotContainsString('%5B', $qs);
        $this->assertStringNotContainsString('%5D', $qs);
    }

    #[Test]
    public function singleValueFilterWithEmptyValues(): void
    {
        $opts = new ListOptions(filters: [
            new FilterEntry('field', FilterOperator::Eq, []),
        ]);
        $this->assertSame('filter[field][eq]=', $opts->toQueryString());
    }
}
