<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInteraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->status === 'active');
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request);
        $validated['is_active'] = $request->boolean('is_active', true);

        $customer = Customer::create($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'orders' => fn ($query) => $query->latest()->limit(10),
            'interactions.user',
            'whatsappMessages' => fn ($query) => $query->latest()->limit(10),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'stats' => [
                'orders_count' => $customer->ordersCount(),
                'total_spent' => $customer->totalSpent(),
                'average_ticket' => $customer->averageTicket(),
                'last_order' => $customer->lastOrder(),
            ],
            'interactionTypes' => CustomerInteraction::typeLabels(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validateCustomer($request, $customer);
        $validated['is_active'] = $request->boolean('is_active');

        $customer->update($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    public function openComanda(Customer $customer): RedirectResponse
    {
        $comanda = (int) config('restaurant.counter_comanda_number', 950);

        session([
            'comanda_customer_id' => $customer->id,
            'comanda_customer_name' => $customer->name,
        ]);

        return redirect()->route('comandas.show', ['comanda' => $comanda, 'add' => 1])
            ->with('success', 'Comanda aberta para '.$customer->name.'. Adicione os produtos.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->exists()) {
            return back()->with('error', 'Não é possível excluir um cliente com pedidos vinculados.');
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cliente excluído com sucesso.');
    }

    public function storeInteraction(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:note,call,email,visit,complaint,feedback'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $customer->interactions()->create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Interação registrada com sucesso.');
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email,'.($customer?->id ?? 'NULL')],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
