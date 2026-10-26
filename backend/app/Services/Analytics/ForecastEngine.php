<?php

declare(strict_types=1);

namespace App\Services\Analytics;

class ForecastEngine
{
    /** @param list<float|int> $values */
    public function predict(array $values, int $horizon, bool $nonnegative = false, bool $integer = false): array
    {
        $n = count($values);
        if ($n < 3) {
            return ['points' => [], 'model' => null, 'confidence' => 'unavailable', 'score' => 0, 'mae' => null];
        }
        $models = ['linear_regression', 'moving_average'];
        if (min($values) > 0) {
            $models[] = 'growth_rate';
        }
        $scores = [];
        foreach ($models as $model) {
            $errors = [];
            for ($cut = max(3, $n - 12); $cut < $n; $cut++) {
                $estimate = $this->estimate(array_slice($values, 0, $cut), $model, 1);
                $estimate = $nonnegative ? max(0, $estimate) : $estimate;
                $errors[] = abs($values[$cut] - $estimate);
            }
            $scores[$model] = $errors === [] ? null : array_sum($errors) / count($errors);
        }
        $model = 'moving_average';
        $best = INF;
        foreach ($scores as $candidate => $error) {
            if ($error !== null && $error < $best) {
                $model = $candidate;
                $best = $error;
            }
        }
        $meanAbsolute = array_sum(array_map('abs', $values)) / $n;
        $mae = $scores[$model];
        $variation = sqrt(array_sum(array_map(fn ($value) => ($value - array_sum($values) / $n) ** 2, $values)) / $n);
        $error = $mae ?? $variation;
        $relativeError = $meanAbsolute > 0 ? $error / $meanAbsolute : ($error > 0 ? 1 : 0);
        $confidence = $n >= 24 && $relativeError <= .15 ? 'high' : ($n >= 12 && $relativeError <= .35 ? 'medium' : 'low');
        if ($horizon > $n) {
            $confidence = 'low';
        }
        $points = [];
        for ($step = 1; $step <= $horizon; $step++) {
            $value = $this->estimate($values, $model, $step);
            $value = $nonnegative ? max(0, $value) : $value;
            $width = 1.5 * $error * sqrt($step);
            $lower = $nonnegative ? max(0, $value - $width) : $value - $width;
            $points[] = [
                'value' => round($value, $integer ? 0 : 2),
                'lower_bound' => $integer ? floor($lower) : round($lower, 2),
                'upper_bound' => $integer ? ceil($value + $width) : round($value + $width, 2),
            ];
        }

        return [
            'points' => $points, 'model' => $model, 'confidence' => $confidence,
            'score' => (int) round(100 * min(1, $n / 24) * max(0, 1 - min(1, $relativeError))),
            'mae' => $mae === null ? null : round($mae, 2),
        ];
    }

    private function estimate(array $values, string $model, int $step): float
    {
        $n = count($values);
        if ($model === 'moving_average') {
            $recent = array_slice($values, -3);

            return array_sum($recent) / count($recent);
        }
        if ($model === 'growth_rate') {
            $rates = [];
            for ($i = 1; $i < $n; $i++) {
                $rates[] = max(-.5, min(.5, ($values[$i] - $values[$i - 1]) / $values[$i - 1]));
            }
            sort($rates);
            $middle = intdiv(count($rates), 2);
            $rate = count($rates) % 2 === 0 ? ($rates[$middle - 1] + $rates[$middle]) / 2 : $rates[$middle];

            return $values[$n - 1] * (1 + $rate) ** $step;
        }
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($values) / $n;
        $numerator = 0;
        $denominator = 0;
        foreach ($values as $x => $value) {
            $numerator += ($x - $meanX) * ($value - $meanY);
            $denominator += ($x - $meanX) ** 2;
        }
        $slope = $denominator > 0 ? $numerator / $denominator : 0;

        return $meanY + $slope * ($n - 1 + $step - $meanX);
    }
}
