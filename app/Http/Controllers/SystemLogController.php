<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only view of storage/logs/laravel.log -- production has no SSH
 * access, so this is the only way to see a real exception/stack trace
 * without a one-off temporary route. Admin-only regardless of the
 * role_page_access configuration (the log can contain request data), same
 * defense-in-depth approach as CloudBackupController.
 */
class SystemLogController extends Controller
{
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can view the system log.');
        }
    }

    private function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-system-log');
    }

    /*
    |--------------------------------------------------------------------------
    | TAIL -- last $chars characters of the log, optionally filtered to
    | lines containing $search (case-insensitive substring match on each
    | blank-line-separated log entry, not each raw line, so a matching
    | exception's full stack trace stays intact).
    |--------------------------------------------------------------------------
    */

    public function tail(Request $request)
    {
        $this->ensureAdmin();

        $path = $this->logPath();

        if (!file_exists($path)) {

            return response()->json([
                'status' => true,
                'exists' => false,
                'content' => '',
                'size' => 0,
            ]);
        }

        $size = filesize($path);

        $chars = (int) $request->get('chars', 20000);
        $chars = max(1000, min($chars, 200000));

        $handle = fopen($path, 'r');
        $start = max(0, $size - $chars);
        fseek($handle, $start);
        $content = fread($handle, $size - $start);
        fclose($handle);

        $search = trim((string) $request->get('search', ''));

        if ($search !== '') {

            $entries = preg_split('/\n(?=\[\d{4}-\d{2}-\d{2})/', $content);

            $content = collect($entries)
                ->filter(fn ($entry) => stripos($entry, $search) !== false)
                ->implode("\n");
        }

        return response()->json([
            'status' => true,
            'exists' => true,
            'content' => $content,
            'size' => $size,
            'truncated' => $start > 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR -- empties the log file in place (doesn't delete/rotate it,
    | since some log channels keep an open file handle for the request's
    | lifetime). Useful housekeeping given the file only ever grows on a
    | host with no SSH-based log rotation.
    |--------------------------------------------------------------------------
    */

    public function clear()
    {
        $this->ensureAdmin();

        $path = $this->logPath();

        if (file_exists($path)) {
            file_put_contents($path, '');
        }

        return response()->json([
            'status' => true,
            'message' => 'Log file cleared.',
        ]);
    }
}
