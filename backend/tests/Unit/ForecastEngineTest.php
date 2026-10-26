<?php

namespace Tests\Unit;

use App\Services\Analytics\ForecastEngine;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ForecastEngineTest extends TestCase
{
    #[TestWith([[10, 15, 20, 25], 30])]
    #[TestWith([[30, 25, 20, 15], 10])]
    #[TestWith([[10, 10, 10, 10], 10])]
    #[TestWith([[-10, -15, -20, -25], -30])]
    public function test_extrapolates_known_trends_without_clamping_negative_profit(array $history, int $expected): void
    {
        $forecast = (new ForecastEngine)->predict($history, 2);
        $this->assertSame((float) $expected, (float) $forecast['points'][0]['value']);
        $this->assertSame('low', $forecast['confidence']);
    }

    #[TestWith([[]])]
    #[TestWith([[10]])]
    #[TestWith([[10, 20]])]
    public function test_withholds_forecast_without_three_complete_periods(array $history): void
    {
        $result = (new ForecastEngine)->predict($history, 3);
        $this->assertSame([], $result['points']);
        $this->assertSame('unavailable', $result['confidence']);
    }

    public function test_clamps_revenue_and_order_bounds_without_fabricating_negative_sales(): void
    {
        $result = (new ForecastEngine)->predict([30, 20, 10, 0], 12, true, true);
        $this->assertSame(0.0, (float) $result['points'][0]['value']);
        $this->assertSame(0.0, (float) $result['points'][11]['lower_bound']);
    }

    public function test_long_stable_history_has_higher_reliability_and_uses_historical_validation(): void
    {
        $result = (new ForecastEngine)->predict(array_fill(0, 24, 20), 3, true);
        $this->assertSame('high', $result['confidence']);
        $this->assertSame(0.0, (float) $result['mae']);
        $this->assertSame(100, $result['score']);
    }
}
