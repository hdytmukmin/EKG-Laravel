<?php

namespace Tests\Unit;

use App\Services\DeepLearningEcgClassificationService;
use ReflectionMethod;
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

    public function test_model_configuration_is_read_from_laravel_config(): void
    {
        config([
            'ekg.minimum_samples' => 2,
            'ekg.deep_learning.enabled' => false,
        ]);

        $result = app(DeepLearningEcgClassificationService::class)
            ->classify([0.1, 0.2], 250);

        $this->assertSame('PENDING_MODEL', $result['label']);
        $this->assertSame('Model deep learning belum diaktifkan.', $result['error_message']);
    }

    public function test_af_subtypes_are_normalized_to_binary_af_label(): void
    {
        $service = app(DeepLearningEcgClassificationService::class);
        $normalize = new ReflectionMethod($service, 'normalizeLabel');

        $this->assertSame('AF', $normalize->invoke($service, 'Persistent AF'));
        $this->assertSame('AF', $normalize->invoke($service, 'Paroxysmal AF'));
        $this->assertSame('NON_AF', $normalize->invoke($service, 'Non-AF'));
    }
}
