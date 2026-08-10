<?php

use App\Http\Controllers\ComandaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryAreaController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPrintController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Public\MenuController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockCategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Waiter\WaiterComandaController;
use App\Http\Controllers\Waiter\WaiterOrderController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

$publicMenuRoutes = function (): void {
    Route::get('/', [MenuController::class, 'index'])->name('menu');
    Route::get('/carrinho', [MenuController::class, 'cart'])->name('cart');
    Route::post('/carrinho', [MenuController::class, 'add'])->name('cart.add');
    Route::patch('/carrinho', [MenuController::class, 'update'])->name('cart.update');
    Route::delete('/carrinho', [MenuController::class, 'remove'])->name('cart.remove');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/pedido-confirmado', [CheckoutController::class, 'success'])->name('checkout.success');
};

$publicDomain = config('digital_menu.public_domain');

if (filled($publicDomain)) {
    Route::domain($publicDomain)->name('public.')->group($publicMenuRoutes);

    Route::get('/cardapio/{path?}', function (?string $path = null) {
        $target = \App\Support\DigitalMenu::publicUrl($path ? '/'.$path : '/');

        return redirect()->away($target, 301);
    })->where('path', '.*');

    Route::get('/', function () {
        if (auth()->check()) {
            return redirect(auth()->user()->homeRoute());
        }

        return redirect()->route('login');
    });
} else {
    Route::redirect('/', '/cardapio');
    Route::prefix('cardapio')->name('public.')->group($publicMenuRoutes);
}

Route::middleware(['auth', 'verified', 'role.staff'])->group(function () {
    Route::prefix('garcom')->name('waiter.')->middleware('waiter.cart')->group(function () {
        Route::get('/', [WaiterOrderController::class, 'menu'])->name('menu');
        Route::post('/comanda', [WaiterOrderController::class, 'setComanda'])->name('comanda');
        Route::get('/carrinho', [WaiterOrderController::class, 'cart'])->name('cart');
        Route::get('/carrinho/resumo', [WaiterOrderController::class, 'cartSummary'])->name('cart.summary');
        Route::post('/carrinho', [WaiterOrderController::class, 'add'])->name('cart.add');
        Route::patch('/carrinho', [WaiterOrderController::class, 'update'])->name('cart.update');
        Route::delete('/carrinho', [WaiterOrderController::class, 'remove'])->name('cart.remove');
        Route::post('/enviar', [WaiterOrderController::class, 'store'])->name('store');
        Route::get('/imprimir/{order}', [WaiterOrderController::class, 'autoprint'])->name('autoprint');
        Route::get('/confirmado', [WaiterOrderController::class, 'success'])->name('success');
        Route::get('/pedidos', [WaiterOrderController::class, 'orders'])->name('orders');
        Route::patch('/pedido/{order}/entregar', [WaiterOrderController::class, 'markServed'])->name('orders.serve');

        Route::get('/comandas', [WaiterComandaController::class, 'index'])->name('comandas.index');
        Route::post('/comanda/{comanda}/abrir', [WaiterComandaController::class, 'open'])->name('comandas.open');
        Route::get('/comanda/{comanda}', [WaiterComandaController::class, 'show'])->name('comandas.show');
        Route::post('/comanda/{comanda}/fechar', [WaiterComandaController::class, 'close'])->name('comandas.close');
        Route::get('/comanda/{comanda}/imprimir-conta', [WaiterComandaController::class, 'autoprint'])->name('comandas.autoprint');
        Route::get('/comanda-fechada', [WaiterComandaController::class, 'closed'])->name('comandas.closed');

        Route::redirect('/mesas', '/garcom/comandas');
        Route::redirect('/mesa-fechada', '/garcom/comanda-fechada');
    });

    Route::middleware('role.admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::get('recipes/{recipe}/imprimir', [RecipeController::class, 'print'])->name('recipes.print');
        Route::resource('recipes', RecipeController::class)->except(['show']);
        Route::resource('delivery-areas', DeliveryAreaController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('stock-categories', StockCategoryController::class)->except(['show']);
        Route::resource('ingredients', IngredientController::class)->except(['show']);
        Route::get('ingredients/{ingredient}/movement', [IngredientController::class, 'movementForm'])->name('ingredients.movement');
        Route::post('ingredients/{ingredient}/movement', [IngredientController::class, 'storeMovement'])->name('ingredients.movement.store');

        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('orders/{order}/print', [OrderPrintController::class, 'show'])->name('orders.print');
        Route::post('orders/{order}/print/network', [OrderPrintController::class, 'network'])->name('orders.print.network');

        Route::get('comandas', [ComandaController::class, 'index'])->name('comandas.index');
        Route::post('comandas/abrir', [ComandaController::class, 'openManual'])->name('comandas.open.manual');
        Route::post('comandas/{comanda}/abrir', [ComandaController::class, 'open'])->name('comandas.open');
        Route::get('comandas/{comanda}', [ComandaController::class, 'show'])->name('comandas.show');
        Route::post('comandas/{comanda}/fechar', [ComandaController::class, 'close'])->name('comandas.close');

        Route::resource('customers', CustomerController::class);
        Route::get('customers/{customer}/comanda', [CustomerController::class, 'openComanda'])->name('customers.comanda');
        Route::post('customers/{customer}/interactions', [CustomerController::class, 'storeInteraction'])->name('customers.interactions.store');
        Route::get('whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::put('settings/restaurant', [SettingsController::class, 'updateRestaurant'])->name('settings.restaurant.update');
        Route::put('settings/digital-menu', [SettingsController::class, 'updateDigitalMenu'])->name('settings.digital-menu.update');
        Route::put('settings/printing', [SettingsController::class, 'updatePrinting'])->name('settings.printing.update');
        Route::put('settings/integration', [SettingsController::class, 'updateIntegration'])->name('settings.integration.update');
        Route::put('settings/whatsapp-agent', [SettingsController::class, 'updateWhatsappAgent'])->name('settings.whatsapp-agent.update');
        Route::post('settings/integration/regenerate-token', [SettingsController::class, 'regenerateIntegrationToken'])->name('settings.integration.regenerate-token');
        Route::post('settings/printing/test', [SettingsController::class, 'testPrinting'])->name('settings.printing.test');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
