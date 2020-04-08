<?php

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function() {

        Route::middleware(['auth:api', 'locked.tenant'])->group(function() {
            
            Route::prefix('purchases')->group(function () {
                Route::get('records', 'Api\PurchaseController@records');
                Route::get('tables', 'Api\PurchaseController@tables');
                Route::get('item-tables', 'Api\PurchaseController@item_tables');
                Route::get('table/{table}', 'Api\PurchaseController@table');
                Route::post('', 'Api\PurchaseController@store');
            });

        }); 

    });
}
