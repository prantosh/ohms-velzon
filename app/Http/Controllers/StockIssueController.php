<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockIssue;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class StockIssueController extends Controller
{
    private const MODULE_CODE = 'STOCK_ISSUE';

    public function index()
    {
        return view('apps-stock-issue');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = StockIssue::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('issue_no', 'like', "%{$search}%")
                    ->orWhere('issued_to_name', 'like', "%{$search}%");
            });
        }

        $issues = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $issues->getCollection()->transform(function ($row) {

            $row->issue_date_fmt = $row->issue_date
                ? \Carbon\Carbon::parse($row->issue_date)->format('d-m-Y')
                : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $issues->items(),
            'pagination' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'total' => $issues->total()
            ]
        ]);
    }

    public function show($id)
    {
        $issue = StockIssue::with('items.item')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $issue
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'issue_date' => 'required|date',
            'issued_to' => 'nullable|exists:users,id',
            'issued_to_name' => 'required|string|max:150',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.uom' => 'required|string|max:20',
            'items.*.issue_qty' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();

        try {

            $items = collect($request->items)
                ->groupBy('inventory_item_id')
                ->map(function ($rows) {

                    $first = $rows->first();

                    return [
                        'inventory_item_id' => $first['inventory_item_id'],
                        'uom' => $first['uom'],
                        'issue_qty' => $rows->sum('issue_qty'),
                    ];
                })
                ->values();

            foreach ($items as $line) {

                $item = InventoryItem::findOrFail($line['inventory_item_id']);

                if ($item->current_stock < $line['issue_qty']) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => "Insufficient stock for {$item->item_name}. Available: {$item->current_stock} {$item->uom}."
                    ], 422);
                }
            }

            $issue = StockIssue::create([

                'issue_no' => $this->generateIssueNo(),
                'issue_date' => $request->issue_date,
                'issued_to' => $request->issued_to,
                'issued_to_name' => $request->issued_to_name,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            foreach ($items as $line) {

                $item = InventoryItem::findOrFail($line['inventory_item_id']);

                $amount = round($line['issue_qty'] * $item->avg_rate, 2);

                $issue->items()->create([

                    'inventory_item_id' => $line['inventory_item_id'],
                    'uom' => $line['uom'],
                    'issue_qty' => $line['issue_qty'],
                    'unit_rate' => $item->avg_rate,
                    'amount' => $amount,
                ]);

                $item->decrement('current_stock', $line['issue_qty']);
                $item->update(['updated_by' => Auth::id()]);
            }

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $issue,
                $issue->only($issue->getFillable()),
                'Stock issued'
            );

            return response()->json([
                'status' => true,
                'issue_id' => $issue->id,
                'issue_no' => $issue->issue_no,
                'message' => 'Stock issued successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to record stock issue.'
            ], 500);
        }
    }

    private function generateIssueNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('ISS/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $last = StockIssue::where('issue_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($last) {

            $explode = explode('/', $last->issue_no);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
