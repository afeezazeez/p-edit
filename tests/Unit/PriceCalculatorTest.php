<?php

use App\Pixelforge\PriceCalculator;

it('estimates the expected price at the reasoning midpoint and returns its range', function () {
    $pricing = pixelforgePricing();
    $estimate = app(PriceCalculator::class)->estimate(
        str_repeat('a', 400), 2, '1536x1024', 'high', $pricing,
    );

    expect($estimate['provider_cost'])->toBe('0.33012500000')
        ->and($estimate['estimated_cost'])->toBe('0.41265625000')
        ->and($estimate['estimated_min_cost'])->toBe('0.35796875000')
        ->and($estimate['estimated_max_cost'])->toBe('0.46734375000')
        ->and($estimate['estimated_usage'])->toBe([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 4500,
        ]);
});

it('calculates final provider and user charges from actual token usage', function () {
    $calculator = app(PriceCalculator::class);
    $rates = pixelforgePricing()['rates'];
    $usage = [
        'input_text_tokens' => 12,
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
    ];

    expect($calculator->providerCost($usage, $rates))->toBe('0.30551000000')
        ->and($calculator->actualCost($usage, $rates))->toBe('0.38188750000');
});

function pixelforgePricing(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}
