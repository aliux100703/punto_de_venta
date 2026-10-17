<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function start(Request $request)
    {
        abort_unless($request->user()->hasRole('Vendedor'), 403);
        $cash = $request->user()->cashRegisters()->where('status', 'ABIERTA')->first();

        return view('shift.start', compact('cash'));
    }

    public function decline(Request $request)
    {
        abort_unless($request->user()->hasRole('Vendedor'), 403);
        abort_if($request->user()->cashRegisters()->where('status', 'ABIERTA')->exists(), 409, 'Ya tienes una caja abierta. Finaliza el turno para cerrarla.');
        AuditLogger::record('cash_declined', $request->user());

        return to_route('dashboard')->with('warning', 'Modo consulta. Abre tu caja para habilitar las operaciones.');
    }

    public function end(Request $request)
    {
        abort_unless($request->user()->hasRole('Vendedor'), 403);
        $cash = $request->user()->cashRegisters()->where('status', 'ABIERTA')->first();
        $expected = $cash ? round((float) $cash->opening_amount + (float) $cash->movements()->where('type', 'ENTRADA')->sum('amount') - (float) $cash->movements()->where('type', 'SALIDA')->sum('amount'), 2) : 0;

        return view('shift.end', compact('cash', 'expected'));
    }
}
