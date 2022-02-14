<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => "user"], function () {
    Route::post('login', "UserController@login")->name("user.login");
    Route::post('register', "UserController@register")->name("user.register");
    Route::post('register-completion', "UserController@registerCompletion")->name("user.register.completion");
    Route::post('logout', "UserController@logout")->name("user.logout")->middleware('User');

    Route::post('me', "UserController@me")->name("user.me")->middleware('User');
    Route::post('profile/{username}', "UserController@profile")->name('user.profile');
    Route::post('update', "UserController@update")->name("user.update")->middleware('User');
});

Route::group(['prefix' => "otp"], function () {
    Route::post('auth', "OtpController@auth");
    Route::post('resend', "OtpController@resend");
});

Route::group(['prefix' => "social"], function () {
    Route::post('store', "SocialLinkController@store")->middleware('User');
    Route::post('delete', "SocialLinkController@delete")->middleware('User');
    Route::post('update', "SocialLinkController@update")->middleware('User');
    Route::post('/', "SocialLinkController@get");
});

Route::group(['prefix' => "category"], function () {
    Route::post('store', "UserCategoryController@store")->middleware('User');
    Route::post('update', "UserCategoryController@update")->middleware('User');
    Route::post('delete', "UserCategoryController@delete")->middleware('User');
    Route::get('/{id?}/{type}', "UserCategoryController@getItems");
    Route::post('/{id?}', "UserCategoryController@get");
});

Route::group(['prefix' => "link"], function () {
    Route::post('store', "LinkController@store")->middleware('User');
    Route::post('update', "LinkController@update")->middleware('User');
    Route::post('delete', "LinkController@delete")->middleware('User');
    Route::post('/{categoryID?}', "LinkController@get");
    Route::post('/{id}/get', "LinkController@getByID");
    Route::post('/{id}/statistic/{filter?}', "LinkController@statistic"); // available filter : month, semester
    Route::post('/{id}/visit', "VisitorController@visitLink");
});

Route::group(['prefix' => "support"], function () {
    Route::post('store', "SupportController@store")->middleware('User');
    Route::post('update', "SupportController@update")->middleware('User');
    Route::post('delete', "SupportController@delete")->middleware('User');
    Route::post('/', "SupportController@get");
    Route::post('/{userID}/user', "SupportController@getByUserID");
    Route::post('/{itemID}/get', "SupportController@getByID");
});

Route::group(['prefix' => "visitor"], function () {
    Route::post('register', "VisitorController@register");
    Route::post('update', "VisitorController@update");

    Route::post('transactions', "VisitorController@transactions");
    Route::post('transactions/{id}/detail', "VisitorController@transactionDetail");

    Route::group(['prefix' => "cart"], function () {
        Route::post('/', "CartController@get");
        Route::post('/store', "CartController@store");
        Route::post('item/{itemID}/{type}', "CartController@increase");
        Route::post('{id}/checkout', "CartController@checkout");
        Route::post('{id}/remove', "CartController@remove");
    });

    Route::group(['prefix' => "shipping"], function () {
        Route::post('courier', "RajaongkirController@courier");
    });
});

Route::group(['prefix' => "video"], function () {
    Route::post('store', "VideoController@store")->middleware('User');
    Route::post('update', "VideoController@update")->middleware('User');
    Route::post('delete', "VideoController@delete")->middleware('User');
    Route::post('priority', "VideoController@priority")->middleware('User');
    Route::post('/', "VideoController@get");
    Route::post('/{id}', "VideoController@getByID");
    Route::post('/{userID}/get', "VideoController@getByUserID");
});

Route::group(['prefix' => "ongkir"], function () {
    Route::post('province', "RajaongkirController@province");
    Route::post('province/{provinceID}/city/{cityID?}', "RajaongkirController@city");
    Route::get('cost', "RajaongkirController@cost");
    Route::get('courier', "RajaongkirController@courier");
});

Route::group(['prefix' => "event"], function () {
    Route::post('store', "EventController@store")->middleware('User');
    Route::post('update', "EventController@update")->middleware('User');
    Route::post('delete', "EventController@delete")->middleware('User');
    Route::post('/{categoryID?}', "EventController@get");
    Route::post('/{id}/get', "EventController@getByID");
});

Route::group(['prefix' => "digital-product"], function () {
    Route::post('store', "DigitalProductController@store")->middleware('User');
    Route::post('update', "DigitalProductController@update")->middleware('User');
    Route::post('delete', "DigitalProductController@delete")->middleware('User');
    Route::post('/{categoryID?}', "DigitalProductController@get");
    Route::post('/{id}/get', "DigitalProductController@getByID");
});

Route::group(['prefix' => "bank"], function () {
    Route::post('store', "BankController@store")->middleware('User');
    Route::post('update', "BankController@update")->middleware('User');
    Route::post('delete', "BankController@delete")->middleware('User');
    Route::post('/{categoryID?}', "BankController@get");
});
