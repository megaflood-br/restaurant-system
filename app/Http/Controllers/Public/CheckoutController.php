<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\OrderPrinterService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('public.menu');
        }

        $context = $cart->all();

        return view('public.checkout', [
            'items' => $cart->items(),
            'total' => $cart->total(),
            'comandaNumber' => $context['comanda_number'],
            'orderType' => $context['type'],
            'deliveryAreas' => DeliveryArea::active()->ordered()->get(),
        ]);
    }

    public function store(Request $request, CartService $cart, OrderPrinterService $printer, InventoryService $inventory): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('public.menu');
        }

        $validated = $request->validate([
            'type' => ['required', 'in:dine_in,delivery,takeaway'],
            'comanda_number' => ['required_if:type,dine_in', 'nullable', 'integer', 'min:1', 'max:999'],
            'delivery_area_id' => [
                'required_if:type,delivery',
                'nullable',
                Rule::exists('delivery_areas', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'delivery_address' => ['required_if:type,delivery', 'nullable', 'string', 'max:500'],
            'customer_name' => ['required_unless:type,dine_in', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_unless:type,dine_in', 'nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['type'] === 'delivery' && ! DeliveryArea::active()->exists()) {
            return back()->withErrors(['type' => 'Delivery indisponível no momento.'])->withInput();
        }

        $deliveryArea = null;
        $deliveryFee = 0;

        if ($validated['type'] === 'delivery') {
            $deliveryArea = DeliveryArea::active()->findOrFail($validated['delivery_area_id']);
            $deliveryFee = (float) $deliveryArea->fee;
        }

        $order = DB::transaction(function () use ($cart, $validated, $deliveryArea, $deliveryFee) {
            $customer = null;

            if (filled($validated['customer_phone'] ?? null)) {
                $customer = Customer::query()
                    ->whereNotNull('phone')
                    ->get()
                    ->first(fn (Customer $c) => PhoneNumber::normalize($c->phone) === PhoneNumber::normalize($validated['customer_phone']))
                    ?? Customer::create([
                        'name' => $validated['customer_name'] ?? 'Cliente Online',
                        'phone' => $validated['customer_phone'],
                        'address' => $validated['delivery_address'] ?? null,
                        'neighborhood' => $deliveryArea?->name,
                        'is_active' => true,
                        'notes' => 'Cadastrado via cardápio online',
                    ]);

                if ($validated['type'] === 'delivery' && $deliveryArea) {
                    $customer->update([
                        'address' => $validated['delivery_address'] ?? $customer->address,
                        'neighborhood' => $deliveryArea->name,
                    ]);
                }
            }

            $comandaNumber = null;
            if ($validated['type'] === 'dine_in') {
                $comandaNumber = (int) ($validated['comanda_number'] ?? $cart->all()['comanda_number']);
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customer?->id,
                'type' => $validated['type'],
                'comanda_number' => $comandaNumber,
                'delivery_area_id' => $deliveryArea?->id,
                'delivery_fee' => $deliveryFee,
                'delivery_address' => $validated['type'] === 'delivery' ? ($validated['delivery_address'] ?? null) : null,
                'customer_name' => $customer?->name ?? ($validated['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? ($validated['customer_phone'] ?? null),
                'notes' => trim((($validated['notes'] ?? '') ? $validated['notes'].' ' : '').'[Cardápio online]'),
                'status' => 'pending',
                'user_id' => null,
            ]);

            $itemsTotal = 0;

            foreach ($cart->items() as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'],
                ]);

                $itemsTotal += $subtotal;
            }

            $order->update(['total' => $itemsTotal + $deliveryFee]);

            return $order;
        });

        $inventory->deductForOrder($order->fresh(['items.product.ingredients']));

        $cart->clear();

        if (config('printing.enabled') && config('printing.driver') === 'network') {
            try {
                $printer->printOrder($order->fresh(['items.product', 'deliveryArea']), 'kitchen');
            } catch (\Throwable) {
                //
            }
        }

        session(['last_order_id' => $order->id]);

        return redirect()->route('public.checkout.success');
    }

    public function success(): View|RedirectResponse
    {
        $orderId = session('last_order_id');

        if (! $orderId) {
            return redirect()->route('public.menu');
        }

        $order = Order::with(['items.product', 'deliveryArea'])->findOrFail($orderId);
        session()->forget('last_order_id');

        return view('public.success', compact('order'));
    }
}
