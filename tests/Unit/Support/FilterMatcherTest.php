<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Support;

use Perfbase\Yii1\Support\FilterMatcher;
use PHPUnit\Framework\TestCase;

class FilterMatcherTest extends TestCase
{
    public function test_matches_wildcard(): void
    {
        self::assertTrue(FilterMatcher::matches(['site/index'], ['*']));
        self::assertTrue(FilterMatcher::matches(['site/index'], ['.*']));
    }

    public function test_matches_regex(): void
    {
        self::assertTrue(FilterMatcher::matches(['GET /users/42'], ['/GET \\/users\\/.+/']));
        self::assertFalse(FilterMatcher::matches(['POST /users'], ['/GET \\/users\\/.+/']));
    }

    public function test_matches_glob(): void
    {
        self::assertTrue(FilterMatcher::matches(['cache/flush'], ['cache/*']));
        self::assertFalse(FilterMatcher::matches(['cache/flush'], ['queue/*']));
    }

    public function test_passes_filters_honors_include_and_exclude(): void
    {
        self::assertTrue(FilterMatcher::passesFilters(['site/index'], ['site/*'], []));
        self::assertFalse(FilterMatcher::passesFilters(['admin/index'], ['site/*'], []));
        self::assertFalse(FilterMatcher::passesFilters(['admin/index'], ['*'], ['admin/*']));
    }
}
