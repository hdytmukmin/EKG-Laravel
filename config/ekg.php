<?php

return [
    'minimum_samples' => (int) env('EKG_MINIMUM_SAMPLES', 5000),
    'sample_rate' => (int) env('EKG_SAMPLE_RATE', 250),
];
