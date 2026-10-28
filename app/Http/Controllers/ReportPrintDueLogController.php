<?php

namespace App\Http\Controllers;

use App\Models\ReportPrintDueLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only view of reports printed while the invoice still had a payment
 * due (see ReportPrintDueLogger). Deliberately NOT in config/access_pages,
 * so it cannot be granted to other roles.
 */
class ReportPrintDueLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(optional(Auth::user())->role === 'Admin', 403, 'Only Admin can view this log.');

        $query = ReportPrintDueLog::query();

        if ($request->filled('from')) {
            $query->whereDate('printed_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('printed_at', '<=', $request->to);
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('printed_by_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('printed_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('apps-report-print-due-log', compact('logs'));
    }
}
