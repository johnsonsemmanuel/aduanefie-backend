<?php

namespace Tests\Unit;

use App\Support\SearchFilterIds;
use PHPUnit\Framework\TestCase;

class SearchFilterIdsTest extends TestCase
{
    public function test_missing_empty_and_scalar_filters_are_safe_countable_lists(): void
    {
        foreach ([null, '', '[]', 'null', '3', 3, false, 'malformed', '{}'] as $input) {
            $this->assertSame([], SearchFilterIds::normalize($input));
            $this->assertSame(0, count(SearchFilterIds::normalize($input)));
        }
    }

    public function test_json_and_array_filters_preserve_valid_ids_and_exclude_invalid_values(): void
    {
        $this->assertSame([2, 7], SearchFilterIds::normalize('[2,"7",2,0,-1,"invalid",null,{}]'));
        $this->assertSame([2, 7], SearchFilterIds::normalize(['2', 7, 2, 0, -1, [], null]));
    }
}
