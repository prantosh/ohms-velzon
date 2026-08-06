<?php

namespace App\Http\Controllers;

use App\Models\RolePageAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DiagnosticTestAdditionalInfoController extends Controller
{
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can manage Diagnostic Test Additional Info tabs.');
        }
    }

    public function index()
    {
        $allowedPages = null;

        if (Auth::user()->role !== 'Admin') {

            $allowedPages = RolePageAccess::where('role', Auth::user()->role)
                ->value('page_access') ?? [];
        }

        $isAdmin = Auth::user()->role === 'Admin';

        // A null page_access_key means "visible to everyone who can open
        // the dashboard" -- that's always true for tabs added later via
        // "Manage Tabs". The 9 original tabs keep a real key, so they
        // still respect role_page_access exactly as before.
        $tabs = DB::table('diagnostic_test_additional_info_tabs')
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($tab) use ($allowedPages) {

                return is_null($tab->page_access_key)
                    || is_null($allowedPages)
                    || in_array($tab->page_access_key, $allowedPages);
            })
            ->map(function ($tab) {

                $tab->url = route($tab->route_name, ['embed' => 1]);
                $tab->slug = 'tab-' . preg_replace('/[^a-z0-9]+/i', '-', $tab->route_name);

                return $tab;
            })
            ->values();

        // The management list (in the "Manage Tabs" modal) always shows
        // every tab to an Admin, regardless of role-based filtering above.
        $allTabsForManagement = $isAdmin
            ? DB::table('diagnostic_test_additional_info_tabs')->orderBy('sort_order')->get()
            : collect();

        return view(
            'apps-diagnostic-test-additional-info',
            compact('tabs', 'isAdmin', 'allTabsForManagement')
        );
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'label' => 'required|string|max:100',
            'route_name' => 'required|string|max:150|unique:diagnostic_test_additional_info_tabs,route_name',
        ]);

        $error = $this->validateRouteName($request->route_name);

        if ($error) {

            return response()->json([
                'status' => false,
                'message' => $error
            ], 422);
        }

        $maxSort = DB::table('diagnostic_test_additional_info_tabs')->max('sort_order') ?? 0;

        DB::table('diagnostic_test_additional_info_tabs')->insert([

            'label' => $request->label,
            'route_name' => $request->route_name,
            'page_access_key' => null,
            'sort_order' => $maxSort + 1,

            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tab added successfully.'
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'label' => 'required|string|max:100',
            'route_name' => 'required|string|max:150|unique:diagnostic_test_additional_info_tabs,route_name,' . $id,
        ]);

        $error = $this->validateRouteName($request->route_name);

        if ($error) {

            return response()->json([
                'status' => false,
                'message' => $error
            ], 422);
        }

        $updated = DB::table('diagnostic_test_additional_info_tabs')
            ->where('id', $id)
            ->update([

                'label' => $request->label,
                'route_name' => $request->route_name,

                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

        if (!$updated) {

            return response()->json([
                'status' => false,
                'message' => 'Tab not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tab updated successfully.'
        ]);
    }

    public function destroy($id)
    {
        $this->ensureAdmin();

        $deleted = DB::table('diagnostic_test_additional_info_tabs')
            ->where('id', $id)
            ->delete();

        if (!$deleted) {

            return response()->json([
                'status' => false,
                'message' => 'Tab not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Tab removed successfully.'
        ]);
    }

    public function move($id, $direction)
    {
        $this->ensureAdmin();

        if (!in_array($direction, ['up', 'down'])) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid direction.'
            ], 422);
        }

        $tabs = DB::table('diagnostic_test_additional_info_tabs')
            ->orderBy('sort_order')
            ->get();

        $index = $tabs->search(fn ($t) => $t->id == $id);

        if ($index === false) {

            return response()->json([
                'status' => false,
                'message' => 'Tab not found.'
            ], 404);
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $tabs->count()) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot move further in that direction.'
            ], 422);
        }

        $current = $tabs[$index];
        $swap = $tabs[$swapIndex];

        DB::table('diagnostic_test_additional_info_tabs')
            ->where('id', $current->id)
            ->update(['sort_order' => $swap->sort_order]);

        DB::table('diagnostic_test_additional_info_tabs')
            ->where('id', $swap->id)
            ->update(['sort_order' => $current->sort_order]);

        return response()->json([
            'status' => true,
            'message' => 'Reordered successfully.'
        ]);
    }

    private function validateRouteName(string $routeName): ?string
    {
        if (!Route::has($routeName)) {

            return "Route \"{$routeName}\" does not exist. Check the route name with your developer.";
        }

        $route = Route::getRoutes()->getByName($routeName);

        if (!empty($route->parameterNames())) {

            return "Route \"{$routeName}\" requires parameters and can't be used as a tab -- it must be a plain index page.";
        }

        if (!in_array('GET', $route->methods())) {

            return "Route \"{$routeName}\" is not a GET route and can't be opened as a page.";
        }

        return null;
    }
}
