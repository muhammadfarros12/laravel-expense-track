<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('expenses:generate-recurring-expense')
    ->dailyAt('00:00')
    // ->everyFifteenSeconds()
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Successfully generated recurring expenses.');
    })
    ->onFailure(function () {
        \Log::error('Failed to generate recurring expenses.');
    });
