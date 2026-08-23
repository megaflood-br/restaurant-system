<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = now()->startOfMonth();
        $todayQuery = Order::query()->whereDate('created_at', today());
        $monthQuery = Order::query()->where('created_at', '>=', $monthStart);
        $stockSummary = Ingredient::summarizeStockValue();
        $lowStockSummary = Ingredient::summarizeStockValue(
            Ingredient::query()->whereColumn('current_stock', '<=', 'minimum_stock')
        );

        return view('dashboard', [
            'stats' => [
                'customers' => Customer::where('is_active', true)->count(),
                'products' => Product::count(),
                'orders_today' => (clone $todayQuery)->count(),
                'orders_month' => (clone $monthQuery)->count(),
                'revenue_today' => (clone $todayQuery)->where('status', '!=', 'cancelled')->sum('total'),
                'revenue_month' => (clone $monthQuery)->where('status', '!=', 'cancelled')->sum('total'),
                'orders_in_progress' => Order::whereIn('status', ['pending', 'preparing', 'ready', 'served'])->count(),
                'low_stock_count' => Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')->count(),
                'stock_total_value' => $stockSummary['total_value'],
                'stock_items_count' => $stockSummary['items_count'],
                'stock_unpriced_count' => $stockSummary['unpriced_count'],
                'low_stock_value' => $lowStockSummary['total_value'],
            ],
            'orders_today' => Order::with('items.product', 'customer', 'user')
                ->whereDate('created_at', today())
                ->latest()
                ->limit(10)
                ->get(),
            'pending_orders' => Order::with('items.product', 'customer')
                ->whereIn('status', ['pending', 'preparing', 'ready', 'served'])
                ->latest()
                ->limit(8)
                ->get(),
            'low_stock' => Ingredient::whereColumn('current_stock', '<=', 'minimum_stock')
                ->orderBy('current_stock')
                ->limit(8)
                ->get(),
        ]);
    }
}
