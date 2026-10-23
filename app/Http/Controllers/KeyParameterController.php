<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\KeyParameter;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KeyParameterController extends Controller
{
    private const MODULE_CODE = 'KEY_PARAMETER';

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-key-parameter-master');
    }

    public function list(Request $request)
    {
        $this->ensureAdmin();

        $query = KeyParameter::query()
            ->leftJoin('invoice_item_details as item', 'item.item_code_sub', '=', 'key_parameters.item_code_sub')
            ->leftJoin('invoice_item_masters as category', 'category.item_code', '=', 'item.item_code')
            ->leftJoin('invoice_item_details as group_item', 'group_item.item_code_sub', '=', 'key_parameters.group_item_code_sub')
            ->select(
                'key_parameters.*',
                'item.item_description_sub',
                'item.item_description_sub as report_item_name',
                'item.rate as item_rate',
                'category.item_name as category_name',
                'group_item.item_description_sub as group_item_description',
                'group_item.is_package as group_item_is_package'
            )
            ->orderBy('group_item.item_description_sub')
            ->orderBy('key_parameters.sort_order')
            ->orderBy('key_parameters.parameter_name');

        if ($request->filled('group_item_code_sub')) {
            if ($request->input('group_item_code_sub') === '_standalone') {
                $query->whereNull('key_parameters.group_item_code_sub');
            } else {
                $query->where('key_parameters.group_item_code_sub', $request->input('group_item_code_sub'));
            }
        }

        if ($request->filled('search')) {
            $search = '%'.trim($request->input('search')).'%';

            $query->where(function ($builder) use ($search) {
                $builder->where('key_parameters.parameter_name', 'like', $search)
                    ->orWhere('group_item.item_description_sub', 'like', $search)
                    ->orWhere('key_parameters.item_code_sub', 'like', $search)
                    ->orWhere('item.item_description_sub', 'like', $search);
            });
        }

        $parameters = $query->get()->map(function ($parameter) {
            $triggers = $parameter->trigger_item_code_subs;
            $parameter->trigger_item_code_subs = is_array($triggers)
                ? $triggers
                : (json_decode($triggers, true) ?: []);

            return $parameter;
        });

        $groups = KeyParameter::query()
            ->join('invoice_item_details as group_item', 'group_item.item_code_sub', '=', 'key_parameters.group_item_code_sub')
            ->whereNotNull('key_parameters.group_item_code_sub')
            ->distinct()
            ->orderBy('group_item.item_description_sub')
            ->get([
                'key_parameters.group_item_code_sub as item_code_sub',
                'group_item.item_description_sub',
                'group_item.is_package',
            ]);

        return response()->json([
            'status' => true,
            'groups' => $groups,
            'data' => $parameters,
        ]);
    }

    public function options()
    {
        $this->ensureAdmin();

        $items = InvoiceItemDetail::query()
            ->join('invoice_item_masters as category', 'category.item_code', '=', 'invoice_item_details.item_code')
            ->where('category.test_parameter_required', 'YES')
            ->where('category.status', 1)
            ->where('invoice_item_details.is_outsourced', 0)
            ->where('invoice_item_details.is_report_not_required', 0)
            ->where('invoice_item_details.status', 'Y')
            ->orderBy('category.item_name')
            ->orderBy('invoice_item_details.item_description_sub')
            ->get([
                'invoice_item_details.item_code_sub',
                'invoice_item_details.item_description_sub',
                'invoice_item_details.rate',
                'invoice_item_details.is_package',
                'invoice_item_details.uom',
                'category.item_name as category_name',
            ]);

        $groupItems = InvoiceItemDetail::query()
            ->join('invoice_item_masters as category', 'category.item_code', '=', 'invoice_item_details.item_code')
            ->where('category.status', 1)
            ->where('invoice_item_details.status', 'Y')
            ->where('invoice_item_details.rate', '<>', 0)
            ->orderBy('category.item_name')
            ->orderBy('invoice_item_details.item_description_sub')
            ->get([
                'invoice_item_details.item_code_sub',
                'invoice_item_details.item_description_sub',
                'invoice_item_details.rate',
                'invoice_item_details.is_package',
                'category.item_name as category_name',
            ]);

        return response()->json([
            'status' => true,
            'data' => $items,
            'group_items' => $groupItems,
        ]);
    }

    public function save(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $eligibleItems = InvoiceItemDetail::query()
            ->join('invoice_item_masters as category', 'category.item_code', '=', 'invoice_item_details.item_code')
            ->where('category.test_parameter_required', 'YES')
            ->where('category.status', 1)
            ->where('invoice_item_details.is_outsourced', 0)
            ->where('invoice_item_details.is_report_not_required', 0)
            ->where('invoice_item_details.status', 'Y')
            ->get(['invoice_item_details.item_code_sub', 'invoice_item_details.rate']);
        $eligibleItemCodes = $eligibleItems->pluck('item_code_sub')->all();
        $groupItemCodes = InvoiceItemDetail::query()
            ->join('invoice_item_masters as category', 'category.item_code', '=', 'invoice_item_details.item_code')
            ->where('category.status', 1)
            ->where('invoice_item_details.status', 'Y')
            ->where('invoice_item_details.rate', '<>', 0)
            ->pluck('invoice_item_details.item_code_sub')
            ->all();

        $validated = $request->validate([
            'item_code_sub' => ['required', 'string', Rule::in($eligibleItemCodes)],
            'group_item_code_sub' => ['nullable', 'string', Rule::in($groupItemCodes)],
            'uom' => ['nullable', 'string', 'max:50'],
            'trigger_when_item_billed' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['item_code_sub'] = strtoupper(trim($validated['item_code_sub']));
        $validated['group_item_code_sub'] = filled($validated['group_item_code_sub'] ?? null)
            ? strtoupper(trim($validated['group_item_code_sub']))
            : null;
        if ($validated['group_item_code_sub'] === $validated['item_code_sub']) {
            throw ValidationException::withMessages([
                'group_item_code_sub' => 'A group invoice item cannot also be its own report item.',
            ]);
        }
        $validated['uom'] = filled($validated['uom'] ?? null) ? trim($validated['uom']) : null;
        $validated['is_active'] = (bool) $validated['is_active'];

        $item = InvoiceItemDetail::where('item_code_sub', $validated['item_code_sub'])->firstOrFail();
        $validated['parameter_name'] = $item->item_description_sub;
        $validated['uom'] = $validated['uom'] ?? $item->uom;

        $parentItem = $validated['group_item_code_sub']
            ? InvoiceItemDetail::where('item_code_sub', $validated['group_item_code_sub'])->firstOrFail()
            : null;
        if (filter_var($validated['trigger_when_item_billed'], FILTER_VALIDATE_BOOLEAN)
            && (! $parentItem || (float) $item->rate == 0)) {
            throw ValidationException::withMessages([
                'trigger_when_item_billed' => 'An item can be triggered individually only when it belongs to a group and has a non-zero rate.',
            ]);
        }
        $validated['group_label'] = $parentItem?->item_description_sub;

        $validated['trigger_item_code_subs'] = [$validated['group_item_code_sub'] ?? $validated['item_code_sub']];
        if ($parentItem && filter_var($request->input('trigger_when_item_billed'), FILTER_VALIDATE_BOOLEAN)
            && (float) $item->rate != 0) {
            $validated['trigger_item_code_subs'][] = $validated['item_code_sub'];
        }
        $validated['trigger_item_code_subs'] = array_values(array_unique($validated['trigger_item_code_subs']));

        $existing = KeyParameter::where('item_code_sub', $validated['item_code_sub'])->first();

        $oldData = null;
        $parameter = null;
        $orderChanges = [];

        DB::transaction(function () use ($validated, $existing, &$oldData, &$parameter, &$orderChanges) {
            $oldGroupItemCode = $existing?->group_item_code_sub;

            if ($existing) {
                $oldData = $existing->only($existing->getFillable());
                $existing->fill($validated);
                $existing->save();
                $parameter = $existing;
            } else {
                $parameter = KeyParameter::create($validated);
            }

            if ($validated['group_item_code_sub']) {
                $groupParameters = KeyParameter::query()
                    ->where('group_item_code_sub', $validated['group_item_code_sub'])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->reject(fn (KeyParameter $groupParameter) => $groupParameter->is($parameter))
                    ->values()
                    ->all();

                array_splice(
                    $groupParameters,
                    min((int) $validated['sort_order'], count($groupParameters)),
                    0,
                    [$parameter]
                );

                foreach ($groupParameters as $order => $groupParameter) {
                    if ((int) $groupParameter->sort_order !== $order) {
                        $oldOrderData = $groupParameter->is($parameter)
                            ? null
                            : $groupParameter->only($groupParameter->getFillable());
                        $groupParameter->sort_order = $order;
                        $groupParameter->save();
                        if ($oldOrderData !== null) {
                            $orderChanges[] = [
                                'parameter' => $groupParameter,
                                'old_data' => $oldOrderData,
                                'new_data' => $groupParameter->only($groupParameter->getFillable()),
                            ];
                        }
                    }
                }
            }

            if ($oldGroupItemCode && $oldGroupItemCode !== $validated['group_item_code_sub']) {
                $oldGroupParameters = KeyParameter::query()
                    ->where('group_item_code_sub', $oldGroupItemCode)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($oldGroupParameters as $order => $groupParameter) {
                    if ((int) $groupParameter->sort_order !== $order) {
                        $oldOrderData = $groupParameter->only($groupParameter->getFillable());
                        $groupParameter->sort_order = $order;
                        $groupParameter->save();
                        $orderChanges[] = [
                            'parameter' => $groupParameter,
                            'old_data' => $oldOrderData,
                            'new_data' => $groupParameter->only($groupParameter->getFillable()),
                        ];
                    }
                }
            }
        });

        if ($oldData !== null) {
            $auditService->logUpdate(
                self::MODULE_CODE,
                $parameter,
                $oldData,
                $parameter->only($parameter->getFillable()),
                'Pathology key parameter updated'
            );
        } else {
            $auditService->logCreate(
                self::MODULE_CODE,
                $parameter,
                $parameter->only($parameter->getFillable()),
                'Pathology key parameter created'
            );
        }

        foreach ($orderChanges as $change) {
            $auditService->logUpdate(
                self::MODULE_CODE,
                $change['parameter'],
                $change['old_data'],
                $change['new_data'],
                'Pathology key parameter order changed'
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Pathology key parameter saved.',
        ]);
    }

    public function reorder(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'group_item_code_sub' => ['required', 'string', Rule::exists('invoice_item_details', 'item_code_sub')],
            'item_code_subs' => ['required', 'array', 'min:1'],
            'item_code_subs.*' => ['required', 'string', 'distinct'],
        ]);

        $changes = [];
        DB::transaction(function () use ($validated, &$changes) {
            $groupParameters = KeyParameter::query()
                ->where('group_item_code_sub', $validated['group_item_code_sub'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $currentCodes = $groupParameters->pluck('item_code_sub')->sort()->values()->all();
            $requestedCodes = collect($validated['item_code_subs'])->sort()->values()->all();

            if ($currentCodes !== $requestedCodes) {
                throw ValidationException::withMessages([
                    'item_code_subs' => 'The group items changed. Refresh the dashboard and try again.',
                ]);
            }

            $parametersByCode = $groupParameters->keyBy('item_code_sub');
            foreach ($validated['item_code_subs'] as $order => $itemCodeSub) {
                $parameter = $parametersByCode->get($itemCodeSub);
                if ((int) $parameter->sort_order !== $order) {
                    $oldData = $parameter->only($parameter->getFillable());
                    $parameter->sort_order = $order;
                    $parameter->save();
                    $changes[] = [
                        'parameter' => $parameter,
                        'old_data' => $oldData,
                        'new_data' => $parameter->only($parameter->getFillable()),
                    ];
                }
            }
        });

        foreach ($changes as $change) {
            $auditService->logUpdate(
                self::MODULE_CODE,
                $change['parameter'],
                $change['old_data'],
                $change['new_data'],
                'Pathology key parameter order changed'
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Group item order saved.',
        ]);
    }

    private function ensureAdmin(): void
    {
        abort_unless(optional(Auth::user())->role === 'Admin', 403, 'Only an Admin can manage pathology key parameters.');
    }
}
