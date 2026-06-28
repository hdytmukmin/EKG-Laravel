<?php

namespace App\Services;

class EcgSignalProcessingService
{
    /**
     * @param  list<float>  $values
     * @return array{
     *     filtered_values: list<float>,
     *     r_peak_indices: list<int>,
     *     rr_intervals: list<float>,
     *     bpm: float,
     *     rr: float,
     *     rr_lokal: float,
     *     heart_rate: list<float>,
     *     sdnn: float,
     *     sns: float
     * }
     */
    public function process(array $values, int $sampleRate): array
    {
        $sampleRate = max(1, $sampleRate);
        $filtered = $this->baselineFiltered($values, (int) round($sampleRate * 0.22), 0.42);
        $rPeaks = $this->detectRPeaks($filtered, $sampleRate);
        $rrIntervals = $this->rrIntervals($rPeaks, $sampleRate);
        $bpm = $this->bpmFromRr($rrIntervals) ?? 0.0;
        $rrMean = $rrIntervals ? array_sum($rrIntervals) / count($rrIntervals) : 0.0;
        $rrLocal = $rrIntervals ? end($rrIntervals) : 0.0;

        return [
            'filtered_values' => $this->roundSeries($filtered, 6),
            'r_peak_indices' => $rPeaks,
            'rr_intervals' => $this->roundSeries($rrIntervals, 6),
            'bpm' => round($bpm, 6),
            'rr' => round($rrMean, 6),
            'rr_lokal' => round($rrLocal, 6),
            'heart_rate' => $this->heartRateSeries($rrIntervals),
            'sdnn' => round($this->sdnn($rrIntervals), 6),
            'sns' => round($this->rmssd($rrIntervals), 6),
        ];
    }

    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    public function roundSeries(array $values, int $precision): array
    {
        return array_map(fn (float $value): float => round($value, $precision), $values);
    }

    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    private function baselineFiltered(array $values, int $window, float $scale): array
    {
        $window = max(5, $window);
        $half = intdiv($window, 2);
        $prefix = [0.0];

        foreach ($values as $value) {
            $prefix[] = end($prefix) + $value;
        }

        $filtered = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $half);
            $end = min($count - 1, $i + $half);
            $mean = ($prefix[$end + 1] - $prefix[$start]) / max(1, $end - $start + 1);
            $filtered[] = ($values[$i] - $mean) * $scale;
        }

        return $filtered;
    }

    /**
     * @param  list<float>  $filtered
     * @return list<int>
     */
    private function detectRPeaks(array $filtered, int $sampleRate): array
    {
        if (count($filtered) < 5) {
            return [];
        }

        $max = max($filtered);
        $min = min($filtered);
        $detectNegative = abs($min) > ($max * 1.2);
        $threshold = max(($detectNegative ? abs($min) : $max) * 0.18, 0.03);
        $minDistance = (int) round($sampleRate * 0.32);
        $peaks = [];
        $lastPeak = -$minDistance;

        $count = count($filtered);
        for ($i = 2; $i < $count - 2; $i++) {
            $magnitude = $detectNegative ? abs($filtered[$i]) : $filtered[$i];
            if ($magnitude < $threshold || $i - $lastPeak < $minDistance) {
                continue;
            }

            $isPositivePeak = $filtered[$i] >= $filtered[$i - 1] && $filtered[$i] >= $filtered[$i + 1];
            $isNegativePeak = $filtered[$i] <= $filtered[$i - 1] && $filtered[$i] <= $filtered[$i + 1];

            if ((! $detectNegative && $isPositivePeak) || ($detectNegative && $isNegativePeak)) {
                $peaks[] = $i;
                $lastPeak = $i;
            }
        }

        return $peaks;
    }

    /**
     * @param  list<int>  $rPeaks
     * @return list<float>
     */
    private function rrIntervals(array $rPeaks, int $sampleRate): array
    {
        $rr = [];
        for ($i = 1; $i < count($rPeaks); $i++) {
            $rr[] = ($rPeaks[$i] - $rPeaks[$i - 1]) / $sampleRate;
        }

        return $rr;
    }

    /**
     * @param  list<float>  $rrIntervals
     */
    private function bpmFromRr(array $rrIntervals): ?float
    {
        if (! $rrIntervals) {
            return null;
        }

        $mean = array_sum($rrIntervals) / count($rrIntervals);

        return $mean > 0 ? 60 / $mean : null;
    }

    /**
     * @param  list<float>  $rrIntervals
     * @return list<float>
     */
    private function heartRateSeries(array $rrIntervals): array
    {
        return array_map(fn (float $rr): float => round($rr > 0 ? 60 / $rr : 0, 2), $rrIntervals);
    }

    /**
     * @param  list<float>  $rrIntervals
     */
    private function sdnn(array $rrIntervals): float
    {
        if (count($rrIntervals) < 2) {
            return 0.0;
        }

        $mean = array_sum($rrIntervals) / count($rrIntervals);
        $variance = array_sum(array_map(fn (float $rr): float => ($rr - $mean) ** 2, $rrIntervals)) / (count($rrIntervals) - 1);

        return sqrt($variance) * 1000;
    }

    /**
     * @param  list<float>  $rrIntervals
     */
    private function rmssd(array $rrIntervals): float
    {
        if (count($rrIntervals) < 2) {
            return 0.0;
        }

        $squares = [];
        for ($i = 1; $i < count($rrIntervals); $i++) {
            $diffMs = ($rrIntervals[$i] - $rrIntervals[$i - 1]) * 1000;
            $squares[] = $diffMs ** 2;
        }

        return sqrt(array_sum($squares) / count($squares));
    }
}
