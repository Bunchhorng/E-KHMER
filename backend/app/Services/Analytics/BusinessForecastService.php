<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class BusinessForecastService
{
    public function __construct(private ForecastDataService $data, private ForecastEngine $engine) {}

    public static function invalidate(): void
    {
        try {
            Cache::put('business-analytics:revision', (string) Str::uuid(), 172800);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function forecast(?Shop $shop, array $options, bool $fresh = false): array
    {
        $revision = Cache::get('business-analytics:revision', 'initial');
        $key = 'business-forecast:v1:'.($shop?->id ?? 'marketplace').':'.hash('sha256', json_encode([$revision, now()->toDateString(), $options]));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 300, fn () => $this->generate($shop, $options));
    }

    private function generate(?Shop $shop, array $options): array
    {
        $data = $this->data->prepare($shop?->id, $options);
        $metric = $options['metric'];
        $training = [];
        $lastTrainingDate = null;
        foreach ($data['history'] as $point) {
            if ($point['type'] === 'current' || $point['partial']) {
                continue;
            }
            $value = $point['metrics'][$metric === 'loss' ? 'profit' : $metric];
            if ($value === null) {
                $training = [];
                $lastTrainingDate = null;
            } else {
                $training[] = $value;
                $lastTrainingDate = Carbon::parse($point['start']);
            }
        }
        $date = $this->data->next($this->data->start(Carbon::parse($options['to']), $options['period']), $options['period']);
        $gap = 0;
        if ($lastTrainingDate !== null) {
            for ($next = $this->data->next($lastTrainingDate, $options['period']); $next->lt($date); $next = $this->data->next($next, $options['period'])) {
                $gap++;
            }
        }
        $prediction = $this->engine->predict($training, $options['horizon'] + $gap, ! in_array($metric, ['profit', 'loss'], true), $metric === 'orders');
        $forecast = [];
        foreach (array_slice($prediction['points'], $gap) as $point) {
            if ($metric === 'loss') {
                $point = array_map(fn ($value) => min(0, $value), $point);
            }
            $forecast[] = [
                'period' => $this->data->label($date, $options['period']),
                'start' => $date->toDateString(), 'end' => $this->data->end($date, $options['period'])->toDateString(),
                'type' => 'predicted', ...$point,
            ];
            $date = $this->data->next($date, $options['period']);
        }
        $growth = fn ($current, $previous) => $previous == 0 ? null : round(100 * ($current - $previous) / abs($previous), 2);
        $n = count($training);
        $change = $n >= 2 ? $growth($training[$n - 1], $training[$n - 2]) : null;
        $previousChange = $n >= 3 ? $growth($training[$n - 2], $training[$n - 3]) : null;
        $delta = $n >= 2 ? $training[$n - 1] - $training[0] : null;
        $warnings = ['recorded_expenses_only'];
        if ($data['summary']['missing_cost_items'] > 0) {
            $warnings[] = 'missing_cost_snapshots';
        }
        if ($data['legacy_unallocated_sales']) {
            $warnings[] = 'legacy_unallocated_sales';
        }
        if ($shop === null) {
            $warnings[] = 'marketplace_trading_profit';
        }
        if (array_filter($data['history'], fn ($point) => $point['partial'] || $point['type'] === 'current')) {
            $warnings[] = 'partial_periods_excluded';
        }

        return [
            'scope' => ['shop_id' => $shop?->id, 'shop_name' => $shop?->name, 'kind' => $shop === null ? 'marketplace' : 'shop'],
            ...$options, 'summary' => $data['summary'], 'history' => $data['history'], 'forecast' => $forecast,
            'status' => $forecast !== [] ? 'available' : (in_array($metric, ['profit', 'loss'], true) && $data['summary']['missing_cost_items'] > 0 ? 'missing_costs' : 'insufficient_history'),
            'model' => $prediction['model'], 'confidence' => $prediction['confidence'],
            'confidence_score' => $prediction['score'], 'backtest_mae' => $prediction['mae'], 'data_points' => $n,
            'growth_percentage' => $change,
            'growth_speed' => $change === null || $previousChange === null ? null : (abs($change - $previousChange) < .5 ? 'stable' : ($change > $previousChange ? 'accelerating' : 'slowing')),
            'trend' => $delta === null ? null : (abs($delta) < .01 ? 'stable' : ($delta > 0 ? 'growing' : 'declining')),
            'forecast_total' => $forecast === [] ? null : round(array_sum(array_column($forecast, 'value')) / ($metric === 'average_order_value' ? count($forecast) : 1), 2),
            'warnings' => $warnings, 'generated_at' => now()->toISOString(),
        ];
    }
}
