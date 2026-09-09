<?php

declare(strict_types=1);

use AltDesign\RiffRaff\Http\Controllers\AltSpamController;
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'AltDesign\RiffRaff\Http\Controllers',
    'middleware' => ['statamic.cp.authenticated', 'can:view riffraff'],
], function () {
    Route::get('/alt-design/riffraff', [AltSpamController::class, 'index'])
        ->name('riffraff.index');

    Route::delete('/alt-design/riffraff', [AltSpamController::class, 'destroyAll'])
        ->name('riffraff.destroyAll');

    Route::delete('/alt-design/riffraff/{id}', [AltSpamController::class, 'destroy'])
        ->name('riffraff.destroy');

    Route::post('/alt-design/riffraff/release/{id}', [AltSpamController::class, 'store'])
        ->name('riffraff.store');

    Route::get('/alt-design/riffraff/show/{id}', [AltSpamController::class, 'show'])
        ->name('riffraff.show');
});
