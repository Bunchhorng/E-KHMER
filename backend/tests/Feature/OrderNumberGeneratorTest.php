<?php

namespace Tests\Feature;

use App\Services\OrderNumberGenerator;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    public function test_candidates_keep_the_prefix_and_application_year_with_sufficient_numeric_capacity(): void
    {
        $this->travelTo(now()->setDate(2031, 12, 31)->endOfDay());
        $generator = app(OrderNumberGenerator::class);

        $first = $generator->next();
        $second = $generator->next();

        $this->assertMatchesRegularExpression('/^SV-2031-[0-9]{12}$/', $first);
        $this->assertSame(20, strlen($first));
        $this->assertNotSame($first, $second);
        $this->travel(1)->seconds();
        $this->assertMatchesRegularExpression('/^SV-2032-[0-9]{12}$/', $generator->next());
    }

    public function test_shop_numbers_keep_the_parent_and_position_and_change_on_retry(): void
    {
        $generator = app(OrderNumberGenerator::class);

        $this->assertSame('SV-2031-123456789012-2', $generator->forShop('SV-2031-123456789012', 2, 1));
        $this->assertMatchesRegularExpression('/^SV-2031-123456789012-2-[0-9]{12}$/',
            $generator->forShop('SV-2031-123456789012', 2, 2));
    }
}
