<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['event' => 'nullable|string|max:80', 'user_id' => 'nullable|integer|min:1', 'from' => 'nullable|date', 'to' => 'nullable|date'.($request->filled('from') ? '|after_or_equal:from' : ''), 'search' => 'nullable|string|max:100']);
        $logs = AuditLog::query()->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('actor_name', 'like', '%'.$v.'%')->orWhere('subject_type', 'like', '%'.$v.'%')->orWhere('ip_address', 'like', '%'.$v.'%')))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
