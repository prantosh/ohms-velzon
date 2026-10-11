<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * For AJAX actions that only an Admin may perform: returns the 403 JSON
     * response to hand back, or null when the signed-in user is an Admin.
     */
    protected function denyUnlessAdmin(string $message = 'Only an Admin can do this.')
    {
        if (optional(Auth::user())->role === 'Admin') {
            return null;
        }

        return response()->json([
            'status' => false,
            'message' => $message,
        ], 403);
    }
}
