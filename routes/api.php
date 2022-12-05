<?php

use Ast\ViolatorsReport\Http\Controllers\ViolatorsReportController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'    => 'api/ui',
    /* Для оставления связи со старым проектом */
    'middleware' => ['ui'],
],function(){

    Route::group([
        'prefix' => 'violators-report',
    ], function (){
        Route::post('export', [ViolatorsReportController::class, 'export']);
        Route::post('get-sections',[ViolatorsReportController::class, 'get_sections']);
    });

});

