<?php

use App\Http\Controllers\Api\ComandaApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\IntegrationDocsController;
use App\Http\Controllers\Api\MenuApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\WhatsAppApiController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->name('webhooks.evolution');

Route::prefix('v1')->middleware('integration.api')->name('api.v1.')->group(function () {
    Route::get('/', IntegrationDocsController::class)->name('docs');

    Route::get('menu', [MenuApiController::class, 'index'])->name('menu');

    Route::get('orders', [OrderApiController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderApiController::class, 'store'])->name('orders.store');
    Route::get('orders/by-phone/{phone}', [OrderApiController::class, 'byPhone'])->name('orders.by-phone');
    Route::get('orders/{order}', [OrderApiController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderApiController::class, 'updateStatus'])->name('orders.status');

    Route::get('customers', [CustomerApiController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerApiController::class, 'store'])->name('customers.store');
    Route::get('customers/by-phone/{phone}', [CustomerApiController::class, 'byPhone'])->name('customers.by-phone');
    Route::get('customers/{customer}', [CustomerApiController::class, 'show'])->name('customers.show');

    Route::get('comandas', [ComandaApiController::class, 'index'])->name('comandas.index');
    Route::get('comandas/{comanda}', [ComandaApiController::class, 'show'])->name('comandas.show');

    Route::get('whatsapp/connection', [WhatsAppApiController::class, 'connection'])->name('whatsapp.connection');
    Route::get('whatsapp/messages', [WhatsAppApiController::class, 'messages'])->name('whatsapp.messages.index');
    Route::post('whatsapp/messages', [WhatsAppApiController::class, 'send'])->name('whatsapp.messages.send');
    Route::post('whatsapp/inbound', [WhatsAppApiController::class, 'inbound'])->name('whatsapp.inbound');
});
