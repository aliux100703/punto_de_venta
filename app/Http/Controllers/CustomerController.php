<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['search' => 'nullable|string|max:150'])['search'] ?? '';
        $customers = Customer::when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('rfc', 'like', "%{$search}%")))->orderBy('name')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer(['active' => true])]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function store(Request $request)
    {
        Customer::create($this->data($request));

        return to_route('customers.index')->with('success', 'Cliente registrado.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->data($request));

        return to_route('customers.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Customer $customer)
    {
        $customer->update(['active' => false]);

        return to_route('customers.index')->with('success', 'Cliente desactivado. Su historial se conserva.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150', 'business_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150', 'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10', 'tax_regime' => 'nullable|string|max:100',
            'cfdi_use' => 'nullable|string|max:20', 'active' => 'required|boolean',
        ]);
    }
}
