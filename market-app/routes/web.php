<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\MarketItemController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\TelegramConversationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketController::class, 'index'])->name('market.index');
Route::get('/items/{item:slug}', [MarketController::class, 'show'])->name('market.items.show');
Route::post('/items/{item:slug}/inquiries', [MarketController::class, 'storeInquiry'])->name('market.inquiries.store');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items/{item:slug}', [CartController::class, 'add'])->name('cart.items.add');
Route::patch('/cart/items/{item:slug}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item:slug}', [CartController::class, 'remove'])->name('cart.items.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{order}/thanks', [OrderController::class, 'thanks'])->name('orders.thanks');
Route::redirect('/login', '/admin/login')->name('login');

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('guest')->group(function (): void {
            Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
            Route::post('login', [AuthController::class, 'login'])->name('login.store');
        });

        Route::post('logout', [AuthController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');

        Route::middleware(['auth', 'role:administrator'])->group(function (): void {
            Route::redirect('/', '/admin/items')->name('dashboard');
            Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
            Route::resource('items', MarketItemController::class)->except(['show', 'destroy']);
            Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
            Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
            Route::get('telegram', [TelegramConversationController::class, 'index'])->name('telegram.index');
            Route::get('telegram/{conversation}', [TelegramConversationController::class, 'show'])->name('telegram.show');
        });
    });
