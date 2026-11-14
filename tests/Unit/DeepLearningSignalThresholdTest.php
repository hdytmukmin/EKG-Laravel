<?php

namespace Tests\Unit;

use App\Services\DeepLearningEcgClassificationService;
use Tests\TestCase;

class DeepLearningSignalThresholdTest extends TestCase
{
    public function test_prediction_is_pending_when_signal_has_fewer_than_minimum_samples(): void
    {
        config(['ekg.minimum_samples' => 5000]);

        $result = app(DeepLearningEcgClassificationService::class)
            ->classify(array_fill(0, 4999, 0.1), 250);

        $this->assertSame('PENDING_MODEL', $result['label']);
        $this->assertNull($result['confidence']);
        $this->assertStringContainsString('4.999', $result['error_message']);
        $this->assertStringContainsString('5.000', $result['error_message']);
    }
}
