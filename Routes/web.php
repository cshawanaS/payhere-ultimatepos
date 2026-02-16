<?php

Route::group(['middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'payhere'], function () {
    Route::get('/settings', 'PayHereController@index')->name('payhere.settings');
    Route::post('/settings', 'PayHereController@updateSettings')->name('payhere.update_settings');
    Route::any('/return/{id}', 'PayHereController@paymentReturn')->name('payhere.return');

    // Install routes
    Route::get('/install', 'InstallController@index');
    Route::get('/update', 'InstallController@update');
    Route::get('/uninstall', 'InstallController@uninstall');
});

// PayHere Webhook (Zero-Touch: matches /webhook/* exclusion in VerifyCsrfToken.php)
Route::post('/webhook/payhere/notify', 'PayHereController@notify')->name('payhere.notify');
