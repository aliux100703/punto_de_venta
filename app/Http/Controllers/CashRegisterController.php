<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CashRegisterController extends Controller
{
    public function index()
    {
        $cashRegister = CashRegister::with('movements')
            ->where('user_id', auth()->id())
            ->where('status', 'ABIERTA')
            ->latest('opened_at')
            ->first();

        $history = CashRegister::where('user_id', auth()->id())
            ->where('status', 'CERRADA')
            ->latest('closed_at')
            ->limit(10)
            ->get();

        $summary = [
            'opening' => 0,
            'income' => 0,
            'expense' => 0,
            'expected' => 0,
        ];

        if ($cashRegister) {

            $income = (float) $cashRegister->movements
                ->where('type', 'ENTRADA')
                ->sum('amount');

            $expense = (float) $cashRegister->movements
                ->where('type', 'SALIDA')
                ->sum('amount');

            $opening = (float) $cashRegister->opening_amount;

            $summary = [
                'opening' => $opening,
                'income' => $income,
                'expense' => $expense,
                'expected' => round(
                    $opening + $income - $expense,
                    2
                ),
            ];
        }

        return view(
            'cash.index',
            compact(
                'cashRegister',
                'history',
                'summary'
            )
        );
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'opening_amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'max:9999999999.99',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        try {

            DB::transaction(function () use ($validated) {
                User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

                $existing = CashRegister::where(
                    'user_id',
                    auth()->id()
                )
                    ->where('status', 'ABIERTA')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'opening_amount' => 'Ya tienes una caja abierta.',
                    ]);
                }

                CashRegister::create([
                    'user_id' => auth()->id(),

                    'opened_at' => now(),

                    'closed_at' => null,

                    'opening_amount' => $validated['opening_amount'],

                    'expected_cash' => null,

                    'counted_cash' => null,

                    'difference' => null,

                    'status' => 'ABIERTA',

                    'notes' => $validated['notes'] ?? null,
                ]);
            });

            return redirect()
                ->route('cash.index')
                ->with(
                    'success',
                    'Caja abierta correctamente.'
                );

        } catch (ValidationException $e) {

            throw $e;
        } catch (Throwable $e) {

            report($e);

            return back()->withErrors([
                'cash' => 'No fue posible abrir la caja. Referencia: '.request()->attributes->get('request_id'),
            ]);
        }
    }

    public function close(Request $request)
    {
        $seller = $request->user()->hasRole('Vendedor');
        if ($seller && ! $request->routeIs('shift.finish')) {
            return to_route('shift.end');
        }
        $validated = $request->validate([
            'counted_cash' => [
                'required',
                'numeric',
                'decimal:0,2',
                'max:9999999999.99',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        try {

            DB::transaction(function () use ($validated, $request, $seller) {
                User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();

                $cashRegister = CashRegister::with('movements')
                    ->where('user_id', auth()->id())
                    ->where('status', 'ABIERTA')
                    ->lockForUpdate()
                    ->first();

                if (! $cashRegister) {
                    throw ValidationException::withMessages([
                        'counted_cash' => 'No tienes una caja abierta.',
                    ]);
                }

                if ($seller && (string) $request->input('cash_register_id') !== (string) $cashRegister->id) {
                    throw ValidationException::withMessages(['cash_register_id' => 'La caja cambió. Actualiza la pantalla antes de cerrar.']);
                }
                $income = (float) $cashRegister
                    ->movements
                    ->where('type', 'ENTRADA')
                    ->sum('amount');

                $expense = (float) $cashRegister
                    ->movements
                    ->where('type', 'SALIDA')
                    ->sum('amount');

                $expected = round(
                    (float) $cashRegister->opening_amount
                    + $income
                    - $expense,
                    2
                );

                $counted = round(
                    (float) $validated['counted_cash'],
                    2
                );

                $difference = round(
                    $counted - $expected,
                    2
                );

                $cashRegister->update([
                    'closed_at' => now(),

                    'expected_cash' => $expected,

                    'counted_cash' => $counted,

                    'difference' => $difference,

                    'status' => 'CERRADA',

                    'notes' => $validated['notes']
                        ?? $cashRegister->notes,
                ]);
            });

            if ($seller) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/')->with('success', 'Caja cerrada y sesión finalizada.');
            }

            return redirect()
                ->route('cash.index')
                ->with(
                    'success',
                    'Caja cerrada correctamente.'
                );

        } catch (ValidationException $e) {

            throw $e;
        } catch (Throwable $e) {

            report($e);

            return back()->withErrors([
                'cash' => 'No fue posible cerrar la caja. Referencia: '.request()->attributes->get('request_id'),
            ]);
        }
    }
}
