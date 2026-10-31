<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        // Require admin role
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        // Filtering
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%");
            });
        }

        $auditLogs = $query->paginate(20)->withQueryString();

        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('Administration/AuditLogs', [
            'auditLogs' => $auditLogs,
            'users' => $users,
            'filters' => $request->only(['user_id', 'model_type', 'action', 'date_from', 'date_to', 'search']),
        ]);
    }
}
