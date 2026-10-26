<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;

class ForecastDataService
{
    public function __construct(private FinancialMetricService $financial) {}

    public function start(Carbon $date, string $period): Carbon
    {
        return match ($period) {
            'yearly' => $date->copy()->startOfYear(),
            'quarterly' => $date->copy()->startOfQuarter(),
            default => $date->copy()->startOfMonth(),
        };
    }

    public function end(Carbon $date, string $period): Carbon
    {
        return match ($period) {
            'yearly' => $date->copy()->endOfYear(),
            'quarterly' => $date->copy()->endOfQuarter(),
            default => $date->copy()->endOfMonth(),
        };
    }

    public function next(Carbon $date, string $period): Carbon
    {
        return match ($period) {
            'yearly' => $date->copy()->addYear(),
            'quarterly' => $date->copy()->addMonths(3),
            default => $date->copy()->addMonth(),
        };
    }

    public function label(Carbon $date, string $period): string
    {
        return match ($period) {
            'yearly' => $date->format('Y'),
            'quarterly' => $date->format('Y').'-Q'.$date->quarter,
            default => $date->format('Y-m'),
        };
    }

    public function prepare(?int $shopId, array $options): array
    {
        $from = Carbon::parse($options['from'])->startOfDay();
        $to = Carbon::parse($options['to'])->endOfDay();
        $data = $this->financial->monthly($shopId, $from, $to, $options['currency']);
        $groups = [];
        $total = $this->financial->emptyBucket();
        foreach ($data['buckets'] as $month => $bucket) {
            $total = $this->financial->add($total, $bucket);
            if ($data['first_activity'] === null || $month < $data['first_activity']) {
                continue;
            }
            $start = $this->start(Carbon::parse($month.'-01'), $options['period']);
            $label = $this->label($start, $options['period']);
            $groups[$label] ??= ['start' => $start, 'bucket' => $this->financial->emptyBucket()];
            $groups[$label]['bucket'] = $this->financial->add($groups[$label]['bucket'], $bucket);
        }
        $history = [];
        foreach ($groups as $label => $group) {
            $end = $this->end($group['start'], $options['period']);
            $current = now()->betweenIncluded($group['start'], $end);
            $partial = $group['start']->lt($from) || $end->gt($to);
            $metrics = $this->financial->metrics($group['bucket']);
            $history[] = [
                'period' => (string) $label, 'start' => $group['start']->toDateString(), 'end' => $end->toDateString(),
                'type' => $current ? 'current' : 'actual', 'partial' => $partial,
                'metrics' => $metrics, 'value' => $metrics[$options['metric']],
            ];
        }

        return [
            'history' => $history, 'summary' => $this->financial->metrics($total),
            'legacy_unallocated_sales' => $data['legacy_unallocated_sales'],
        ];
    }
}
