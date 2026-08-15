<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request, CartService $cart): View
    {
        if ($request->filled('comanda')) {
            $cart->setContext((int) $request->query('comanda'), $request->query('tipo', 'dine_in'));
        }

        return view('public.menu', [
            'categories' => MenuCatalog::categories(),
            'cartCount' => $cart->count(),
            'cartTotal' => $cart->total(),
            'comandaNumber' => $cart->all()['comanda_number'],
            'orderType' => $cart->all()['type'],
        ]);
    }

    public function cart(CartService $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('public.menu')->with('info', 'Seu carrinho está vazio.');
        }

        return view('public.cart', [
            'items' => $cart->items(),
            'total' => $cart->total(),
        ]);
    }

    public function add(Request $request, CartService $cart): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => \App\Support\ProductVariants::variantIdRules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = \App\Support\ProductVariants::loadProduct((int) $validated['product_id']);
        \App\Support\ProductSellable::resolve($product, $validated['variant_id'] ?? null);
        \App\Support\ProductSellable::assertAvailableToday($product);

        $cart->add(
            (int) $validated['product_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item adicionado ao carrinho.',
                'cart_count' => $cart->count(),
                'cart_total' => $cart->total(),
            ]);
        }

        return back()->with('success', 'Item adicionado ao carrinho.');
    }

    public function update(Request $request, CartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
        ]);

        $cart->update(
            (int) $validated['product_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        return back();
    }

    public function remove(Request $request, CartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $cart->remove(
            (int) $validated['product_id'],
            $validated['notes'] ?? null,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );

        return back()->with('success', 'Item removido.');
    }
}
