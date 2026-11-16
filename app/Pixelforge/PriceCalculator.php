<?php

namespace App\Pixelforge;

use InvalidArgumentException;

class PriceCalculator
{
    public function estimate(string $prompt, int $referenceCount, string $size, string $quality, array $pricing): array
    {
        $tokens = $this->estimatedTokens($prompt, $referenceCount, $size, $quality, $pricing);
        $providerNanodollars = $this->costInNanodollars($tokens, $pricing['rates']);

        return [
            'provider_cost' => $this->format($providerNanodollars * 100, 11),
            'estimated_cost' => $this->format($providerNanodollars * 125, 11),
            'rates' => $pricing['rates'],
            'estimated_usage' => $tokens,
        ];
    }

    public function actualCost(array $usage, array $rates): string
    {
        return $this->format($this->costInNanodollars($usage, $rates) * 125, 11);
    }

    public function providerCost(array $usage, array $rates): string
    {
        return $this->format($this->costInNanodollars($usage, $rates) * 100, 11);
    }

    private function estimatedTokens(string $prompt, int $referenceCount, string $size, string $quality, array $pricing): array
    {
        $textCount = mb_strlen($prompt, 'UTF-8');
        $charactersPerToken = $pricing['tokens']['characters_per_text_token'];
        $baseTokens = intdiv($textCount + $charactersPerToken - 1, $charactersPerToken);
        $variancePercent = (int) ((float) $pricing['tokens']['text_token_variance'] * 100);
        $textTokens = intdiv($baseTokens * (100 + $variancePercent) + 99, 100);

        return [
            'input_text_tokens' => $textTokens,
            'input_image_tokens' => $referenceCount * $pricing['tokens']['per_reference_image'],
            'output_tokens' => $pricing['tokens']['output'][$quality][$size],
            'reasoning_tokens' => $pricing['tokens']['reasoning'][$quality]['max'],
        ];
    }

    private function costInNanodollars(array $usage, array $rates): int
    {
        $cost = 0;
        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $category) {
            $this->assertInputs($usage, $rates, $category);
            $cost += (int) $usage[$category] * $this->rateCents($rates[$category]) * 10;
        }

        return $cost;
    }

    private function assertInputs(array $usage, array $rates, string $category): void
    {
        if (! isset($usage[$category], $rates[$category]) || ! preg_match('/^\d+$/', (string) $usage[$category])) {
            throw new InvalidArgumentException("Missing or invalid Pixelforge pricing value: {$category}.");
        }
    }

    private function rateCents(string $rate): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $rate, $matches)) {
            throw new InvalidArgumentException('Pixelforge rate must be an exact decimal with at most two places.');
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function format(int $scaledAmount, int $scale): string
    {
        $digits = str_pad((string) $scaledAmount, $scale + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }
}
