<?php

use Illuminate\Support\Facades\Route;
use Ast\ViolatorsReport\Controllers\ViolatorsReportController;

Route::group([
    'prefix' => 'violators-report',
    'middleware' => ['ui'],
], function (){
    Route::post('export', [ViolatorsReportController::class, 'export']);
    Route::post('get-sections',[ViolatorsReportController::class, 'get_sections']);
});