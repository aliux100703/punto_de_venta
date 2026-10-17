<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index()
    {
        return view('expenses.index', [
            'expenses' => Expense::with(['category', 'paymentMethod', 'user'])->latest('expense_date')->paginate(20),
            'categories' => ExpenseCategory::where('active', true)->orderBy('name')->get(),
            'methods' => PaymentMethod::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('active', true)],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('active', true)],
            'concept' => 'required|string|max:200', 'amount' => 'required|numeric|decimal:0,2|gt:0|max:9999999999.99',
            'expense_date' => 'required|date|before_or_equal:today', 'notes' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($data) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $method = PaymentMethod::findOrFail($data['payment_method_id']);
            $cash = null;
            if (mb_strtolower($method->name) === 'efectivo') {
                $cash = CashRegister::where('user_id', auth()->id())->where('status', 'ABIERTA')->lockForUpdate()->first();
                if (! $cash) {
                    throw ValidationException::withMessages(['amount' => 'Abre una caja para registrar gastos en efectivo.']);
                }
                $balance = (float) $cash->opening_amount + (float) $cash->movements()->where('type', 'ENTRADA')->sum('amount') - (float) $cash->movements()->where('type', 'SALIDA')->sum('amount');
                if ((float) $data['amount'] > round($balance, 2)) {
                    throw ValidationException::withMessages(['amount' => 'El gasto supera el efectivo disponible en caja.']);
                }
            }
            $expense = Expense::create($data + ['user_id' => auth()->id()]);
            if ($cash) {
                $cash->movements()->create(['user_id' => auth()->id(), 'type' => 'SALIDA', 'concept' => $expense->concept, 'amount' => $expense->amount, 'reference_type' => 'EXPENSE', 'reference_id' => $expense->id]);
            }
        });

        return to_route('expenses.index')->with('success', 'Gasto registrado.');
    }
}
