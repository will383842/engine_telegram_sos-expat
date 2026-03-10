<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

class TemplateRenderer
{
    /**
     * Render a template by replacing {{VARIABLE}} placeholders with actual values.
     *
     * Missing variables are left as-is (graceful handling).
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($variables) {
            $key = $matches[1];

            if (array_key_exists($key, $variables)) {
                return (string) $variables[$key];
            }

            // Leave placeholder intact if no value provided
            return $matches[0];
        }, $template);
    }

    /**
     * Format an amount in cents to a dollar string.
     *
     * @param int $cents e.g. 5000
     * @return string e.g. "$50.00"
     */
    public function formatMoney(int $cents): string
    {
        $dollars = $cents / 100;

        return '$' . number_format($dollars, 2, '.', ',');
    }

    /**
     * Format a date to dd/MM/yyyy in Paris timezone.
     */
    public function formatDate(string|\DateTimeInterface $date): string
    {
        $carbon = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return $carbon
            ->setTimezone(config('telegram.timezone', 'Europe/Paris'))
            ->format('d/m/Y');
    }

    /**
     * Format a time to HH:mm in Paris timezone.
     */
    public function formatTime(string|\DateTimeInterface $time): string
    {
        $carbon = $time instanceof \DateTimeInterface
            ? Carbon::instance($time)
            : Carbon::parse($time);

        return $carbon
            ->setTimezone(config('telegram.timezone', 'Europe/Paris'))
            ->format('H:i');
    }
}
