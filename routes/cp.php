<?php

declare(strict_types=1);

use AltDesign\SpamAddon\Http\Controllers\AltSpamController;
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'AltDesign\SpamAddon\Http\Controllers',
    'middleware' => ['web', 'statamic.cp.authenticated'],
], function () {
    Route::get('/alt-design/spam-addon', [AltSpamController::class, 'index'])
        ->name('spam-addon.index');

    Route::delete('/alt-design/spam-addon/{id}', [AltSpamController::class, 'destroy'])
        ->name('spam-addon.destroy');

    Route::post('/alt-design/spam-addon/release/{id}', [AltSpamController::class, 'store'])
        ->name('spam-addon.store');

    Route::get('/alt-design/spam-addon/show/{id}', [AltSpamController::class, 'show'])
        ->name('spam-addon.show');
});
