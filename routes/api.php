<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'violators-report',
    'middleware' => ['ui'],
], function (){
    Route::post('export', [ViolatorsReportController::class, 'export']);
    Route::post('get-sections',[ViolatorsReportController::class, 'get_sections']);
});