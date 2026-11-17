<?php

return [
    'minimum_samples' => (int) env('EKG_MINIMUM_SAMPLES', 5000),
    'sample_rate' => (int) env('EKG_SAMPLE_RATE', 250),
    'prediction_queue' => env('EKG_PREDICTION_QUEUE', 'ekg-predictions'),

    'deep_learning' => [
        'enabled' => (bool) env('DL_MODEL_ENABLED', true),
        'model_dir' => env('DL_MODEL_DIR', ''),
        'predict_script' => env('DL_PREDICT_SCRIPT', base_path('scripts/predict_dl_ecg.py')),
        'python_binary' => env('PYTHON_BINARY', env('EKG_PYTHON_BIN', 'python')),
        'timeout' => (int) env('DL_PREDICTION_TIMEOUT', env('EKG_PREDICTION_TIMEOUT', 180)),
        'weights_file' => env('DL_MODEL_WEIGHTS', 'best_dl_modelExp3LSTMTuned.pth'),
    ],
];
