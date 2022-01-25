<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use Marvel\Http\Controllers\AddressController;
use Marvel\Http\Controllers\AttributeController;
use Marvel\Http\Controllers\AttributeValueController;
use Marvel\Http\Controllers\ProductController;
use Marvel\Http\Controllers\SettingsController;
use Marvel\Http\Controllers\UserController;
use Marvel\Http\Controllers\TypeController;
use Marvel\Http\Controllers\OrderController;
use Marvel\Http\Controllers\OrderStatusController;
use Marvel\Http\Controllers\CategoryController;
use Marvel\Http\Controllers\CouponController;
use Marvel\Http\Controllers\AttachmentController;
use Marvel\Http\Controllers\ShippingController;
use Marvel\Http\Controllers\TaxController;
use Marvel\Enums\Permission;
use Marvel\Http\Controllers\AnalyticsController;
use Marvel\Http\Controllers\RefundController;
use Marvel\Http\Controllers\ShopController;
use Marvel\Http\Controllers\TagController;
use Marvel\Http\Controllers\WithdrawController;
use Marvel\Http\Controllers\AuthorController;
use Marvel\Http\Controllers\CheckoutController;
use Marvel\Http\Controllers\DownloadController;
use Marvel\Http\Controllers\ManufacturerController;


Route::post('/register', [UserController::class, 'register']);
Route::post('/token', [UserController::class, 'token']);
Route::post('/logout', [UserController::class, 'logout']);
Route::post('/forget-password', [UserController::class, 'forgetPassword']);
Route::post('/verify-forget-password-token', [UserController::class, 'verifyForgetPasswordToken']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);
Route::post('/contact-us', [UserController::class, 'contactAdmin']);
Route::post('/social-login-token', [UserController::class, 'socialLogin']);
Route::post('/send-otp-code', [UserController::class, 'sendOtpCode']);
Route::post('/verify-otp-code', [UserController::class, 'verifyOtpCode']);
Route::post('/otp-login', [UserController::class, 'otpLogin']);
Route::get('top-authors', [AuthorController::class, 'topAuthor']);
Route::get('top-manufacturers', [ManufacturerController::class, 'topManufacturer']);
Route::get('popular-products', [ProductController::class, 'popularProducts']);



Route::apiResource('products', ProductController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('types', TypeController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('attachments', AttachmentController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('categories', CategoryController::class, [
    'only' => ['index', 'show']
]);
Route::apiResource('tags', TagController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('coupons', CouponController::class, [
    'only' => ['index', 'show']
]);

Route::post('coupons/verify', [CouponController::class, 'verify']);


Route::apiResource('order-status', OrderStatusController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('attributes', AttributeController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('shops', ShopController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('attribute-values', AttributeValueController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('settings', SettingsController::class, [
    'only' => ['index']
]);

Route::apiResource('authors', AuthorController::class, [
    'only' => ['index', 'show']
]);

Route::apiResource('manufacturers', ManufacturerController::class, [
    'only' => ['index', 'show']
]);


Route::post('orders/checkout/verify', [CheckoutController::class, 'verify']);

Route::apiResource('orders', OrderController::class, [
    'only' => ['show', 'store']
]);

Route::group(['middleware' => ['can:' . Permission::CUSTOMER, 'auth:sanctum']], function () {
    Route::apiResource('orders', OrderController::class, [
        'only' => ['index']
    ]);
    Route::apiResource('attachments', AttachmentController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::get('me', [UserController::class, 'me']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/update-contact', [UserController::class, 'updateContact']);
    Route::apiResource('address', AddressController::class, [
        'only' => ['destroy']
    ]);
    Route::apiResource(
        'refunds',
        RefundController::class,
        [
            'only' => ['index', 'store', 'show']
        ]
    );
    Route::get('downloads', [DownloadController::class, 'fetchDownloadableFiles']);
    Route::post('downloads/digital_file', [DownloadController::class, 'generateDownloadableUrl']);
});

Route::group(
    ['middleware' => ['permission:' . Permission::STAFF . '|' . Permission::STORE_OWNER, 'auth:sanctum']],
    function () {
        Route::get('analytics', [AnalyticsController::class, 'analytics']);
        Route::apiResource('products', ProductController::class, [
            'only' => ['store', 'update', 'destroy']
        ]);
        Route::apiResource('attributes', AttributeController::class, [
            'only' => ['store', 'update', 'destroy']
        ]);
        Route::apiResource('attribute-values', AttributeValueController::class, [
            'only' => ['store', 'update', 'destroy']
        ]);
        Route::apiResource('orders', OrderController::class, [
            'only' => ['update', 'destroy']
        ]);
        Route::apiResource('authors', AuthorController::class, [
            'only' => ['store']
        ]);
        Route::apiResource('manufacturers', ManufacturerController::class, [
            'only' => ['store']
        ]);
    }
);

Route::post('import-products', [ProductController::class, 'importProducts']);
Route::post('import-variation-options', [ProductController::class, 'importVariationOptions']);
Route::get('export-products/{shop_id}', [ProductController::class, 'exportProducts']);
Route::get('export-variation-options/{shop_id}', [ProductController::class, 'exportVariableOptions']);
Route::post('import-attributes', [AttributeController::class, 'importAttributes']);
Route::get('export-attributes/{shop_id}', [AttributeController::class, 'exportAttributes']);

Route::group(
    ['middleware' => ['permission:' . Permission::STORE_OWNER, 'auth:sanctum']],
    function () {
        Route::apiResource('shops', ShopController::class, [
            'only' => ['store', 'update', 'destroy']
        ]);
        Route::apiResource('withdraws', WithdrawController::class, [
            'only' => ['store', 'index', 'show']
        ]);
        Route::post('staffs', [ShopController::class, 'addStaff']);
        Route::delete('staffs/{id}', [ShopController::class, 'deleteStaff']);
        Route::get('staffs', [UserController::class, 'staffs']);
        Route::get('my-shops', [ShopController::class, 'myShops']);
    }
);


Route::group(['middleware' => ['permission:' . Permission::SUPER_ADMIN, 'auth:sanctum']], function () {
    Route::apiResource('types', TypeController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('withdraws', WithdrawController::class, [
        'only' => ['update', 'destroy']
    ]);
    Route::apiResource('categories', CategoryController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('tags', TagController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('coupons', CouponController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);
    Route::apiResource('order-status', OrderStatusController::class, [
        'only' => ['store', 'update', 'destroy']
    ]);

    Route::apiResource('settings', SettingsController::class, [
        'only' => ['store']
    ]);
    Route::apiResource('users', UserController::class);
    Route::apiResource('authors', AuthorController::class, [
        'only' => ['update', 'destroy']
    ]);
    Route::apiResource('manufacturers', ManufacturerController::class, [
        'only' => ['update', 'destroy']
    ]);
    Route::post('users/block-user', [UserController::class, 'banUser']);
    Route::post('users/unblock-user', [UserController::class, 'activeUser']);
    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('shippings', ShippingController::class);
    Route::post('approve-shop', [ShopController::class, 'approveShop']);
    Route::post('disapprove-shop', [ShopController::class, 'disApproveShop']);
    Route::post('approve-withdraw', [WithdrawController::class, 'approveWithdraw']);
    Route::post('add-points', [UserController::class, 'addPoints']);
    Route::post('users/make-admin', [UserController::class, 'makeOrRevokeAdmin']);
    Route::apiResource(
        'refunds',
        RefundController::class,
        [
            'only' => ['destroy', 'update']
        ]
    );
});

Route::get(
    'download_url/token/{token}',
    [DownloadController::class, 'downloadFile']
)->name('download_url.token');

Route::post(
    'subscribe-to-newsletter',
    [UserController::class, 'subscribeToNewsletter']
)->name('subscribeToNewsletter');
