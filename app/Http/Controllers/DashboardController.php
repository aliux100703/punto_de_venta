<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(['week', 'month'])]])['period'] ?? 'week';
        $user = $request->user();
        $sales = Sale::visibleTo($user)->whereIn('status', ['COMPLETADA', 'DEVOLUCION_PARCIAL'])->whereDate('created_at', today());
        $revenue = (float) (clone $sales)->sum(DB::raw('total - refunded_amount'));
        $saleCount = (clone $sales)->count();
        $yesterdayRevenue = (float) Sale::visibleTo($user)->whereIn('status', ['COMPLETADA', 'DEVOLUCION_PARCIAL'])->whereDate('created_at', today()->subDay())->sum(DB::raw('total - refunded_amount'));
        $days = $period === 'month' ? 30 : 7;
        $startDate = today()->subDays($days - 1);
        $periodQuery = Sale::visibleTo($user)->whereIn('status', ['COMPLETADA', 'DEVOLUCION_PARCIAL'])->whereBetween('created_at', [$startDate, today()->endOfDay()]);
        $dailyTotals = (clone $periodQuery)->selectRaw('DATE(created_at) as day, SUM(total - refunded_amount) as amount')->groupByRaw('DATE(created_at)')->pluck('amount', 'day');
        $dailySales = collect(range(0, $days - 1))->map(function ($offset) use ($startDate, $dailyTotals) {
            $date = $startDate->copy()->addDays($offset);

            return ['date' => $date->toDateString(), 'label' => $date->locale('es')->isoFormat('D MMM'), 'amount' => (float) ($dailyTotals[$date->toDateString()] ?? 0)];
        });

        return view('dashboard', [
            'revenue' => $revenue, 'saleCount' => $saleCount,
            'expenses' => $user->isAdministrator() ? Expense::whereDate('expense_date', today())->sum('amount') : null,
            'averageTicket' => $saleCount ? $revenue / $saleCount : 0,
            'revenueChange' => $yesterdayRevenue > 0 ? round(($revenue - $yesterdayRevenue) / $yesterdayRevenue * 100, 1) : null,
            'pendingQuotes' => Quote::visibleTo($user)->where('status', 'PENDIENTE')->where(fn ($query) => $query->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today()))->count(),
            'lowStock' => Product::with('unit')->where('active', true)->whereColumn('current_stock', '<=', 'minimum_stock')->orderBy('current_stock')->limit(4)->get(),
            'lowStockCount' => Product::where('active', true)->whereColumn('current_stock', '<=', 'minimum_stock')->count(),
            'recentSales' => Sale::visibleTo($user)->with(['seller', 'customer'])->withCount('details')->latest()->limit(5)->get(),
            'dailySales' => $dailySales, 'period' => $period, 'startDate' => $startDate,
            'periodRevenue' => (float) (clone $periodQuery)->sum(DB::raw('total - refunded_amount')),
            'periodOperations' => (clone $periodQuery)->count(),
        ]);
    }
}
