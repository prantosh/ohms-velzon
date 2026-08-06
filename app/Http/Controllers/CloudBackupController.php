<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use PDO;
use PDOException;

class CloudBackupController extends Controller
{
    private const MODULE_CODE = 'CLOUD_BACKUP';

    /**
     * This screen accepts arbitrary remote database credentials, so it is
     * restricted to Admin regardless of the role_page_access configuration
     * -- defense in depth, matching the lesson learned from the earlier
     * UserController page-access gap.
     */
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can access Cloud Database Backup.');
        }
    }

    private function backupDir(): string
    {
        $dir = storage_path('app/backups');

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-cloud-backup');
    }

    /*
    |--------------------------------------------------------------------------
    | TEST CONNECTION
    |--------------------------------------------------------------------------
    */

    public function testConnection(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'host' => 'required|string|max:191',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:191',
            'username' => 'required|string|max:191',
            'password' => 'nullable|string|max:191',
        ]);

        $error = $this->tryConnect($request);

        if ($error) {
            return response()->json([
                'status' => false,
                'message' => $error,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Connection successful.',
        ]);
    }

    private function tryConnect(Request $request): ?string
    {
        $port = $request->port ?: 3306;

        $dsn = "mysql:host={$request->host};port={$port};dbname={$request->database};charset=utf8mb4";

        try {

            new PDO($dsn, $request->username, (string) $request->password, [
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return null;

        } catch (PDOException $e) {

            return 'Could not connect: ' . $e->getMessage();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RUN BACKUP
    |--------------------------------------------------------------------------
    */

    public function backup(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'host' => 'required|string|max:191',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:191',
            'username' => 'required|string|max:191',
            'password' => 'nullable|string|max:191',
        ]);

        $connectError = $this->tryConnect($request);

        if ($connectError) {

            $log = BackupLog::create([
                'host' => $request->host,
                'database_name' => $request->database,
                'status' => 'FAILED',
                'message' => $connectError,
                'created_by' => Auth::id(),
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $log,
                $log->only($log->getFillable()),
                'Cloud database backup connection failed'
            );

            return response()->json([
                'status' => false,
                'message' => $connectError,
            ]);
        }

        $port = $request->port ?: 3306;
        $safeDbName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $request->database);
        $fileName = 'cloud-backup-' . $safeDbName . '-' . now()->format('Y-m-d_His') . '.sql';
        $filePath = $this->backupDir() . DIRECTORY_SEPARATOR . $fileName;

        set_time_limit(0);

        $dsn = "mysql:host={$request->host};port={$port};dbname={$request->database};charset=utf8mb4";

        try {

            $pdo = new PDO($dsn, $request->username, (string) $request->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);

            $this->dumpDatabase($pdo, $request->database, $filePath);

        } catch (\Throwable $e) {

            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $log = BackupLog::create([
                'file_name' => $fileName,
                'host' => $request->host,
                'database_name' => $request->database,
                'status' => 'FAILED',
                'message' => 'Backup failed: ' . $e->getMessage(),
                'created_by' => Auth::id(),
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $log,
                $log->only($log->getFillable()),
                'Cloud database backup failed'
            );

            return response()->json([
                'status' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ]);
        }

        if (!File::exists($filePath) || File::size($filePath) === 0) {

            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $log = BackupLog::create([
                'file_name' => $fileName,
                'host' => $request->host,
                'database_name' => $request->database,
                'status' => 'FAILED',
                'message' => 'Backup produced an empty file.',
                'created_by' => Auth::id(),
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $log,
                $log->only($log->getFillable()),
                'Cloud database backup produced an empty file'
            );

            return response()->json([
                'status' => false,
                'message' => 'Backup produced an empty file. Please check the remote database and try again.',
            ]);
        }

        $log = BackupLog::create([
            'file_name' => $fileName,
            'host' => $request->host,
            'database_name' => $request->database,
            'size_bytes' => File::size($filePath),
            'status' => 'SUCCESS',
            'created_by' => Auth::id(),
        ]);

        $auditService->logCreate(
            self::MODULE_CODE,
            $log,
            $log->only($log->getFillable()),
            'Cloud database backup completed'
        );

        return response()->json([
            'status' => true,
            'message' => 'Backup completed successfully.',
            'file_name' => $fileName,
            'size' => File::size($filePath),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PHP-NATIVE DUMP (used instead of mysqldump.exe -- see note below)
    |--------------------------------------------------------------------------
    | On Windows, the native mysqldump client can fail with "Got error 2004:
    | Can't create TCP/IP socket (10106)" against some remote hosts when a
    | security product's Winsock LSP hook conflicts with libmysqlclient's
    | socket call, even though the same host connects fine over PDO. Dumping
    | via PDO sidesteps that entirely and removes the mysqldump binary
    | dependency. The result set is read unbuffered (MYSQL_ATTR_USE_BUFFERED_
    | QUERY=false) and streamed straight to disk so large tables don't have
    | to fit in PHP memory at once.
    */

    private function dumpDatabase(PDO $pdo, string $databaseName, string $filePath): void
    {
        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Could not open backup file for writing.');
        }

        try {

            fwrite($handle, "-- ABSSRK Cloud Database Backup\n");
            fwrite($handle, "-- Database: {$databaseName}\n");
            fwrite($handle, "-- Generated: " . now()->toDateTimeString() . "\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

            foreach ($tables as [$tableName]) {
                $this->dumpTableSchema($pdo, $tableName, $handle);
                $this->dumpTableData($pdo, $tableName, $handle);
            }

            $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);

            foreach ($views as [$viewName]) {
                $this->dumpViewSchema($pdo, $viewName, $handle);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");

        } finally {
            fclose($handle);
        }
    }

    private function dumpTableSchema(PDO $pdo, string $tableName, $handle): void
    {
        $quoted = $this->quoteIdentifier($tableName);

        $createSql = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? null;

        if (!$createSql) {
            return;
        }

        fwrite($handle, "\n--\n-- Table structure for {$quoted}\n--\n\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
        fwrite($handle, $createSql . ";\n\n");
    }

    private function dumpTableData(PDO $pdo, string $tableName, $handle): void
    {
        $quoted = $this->quoteIdentifier($tableName);

        $stmt = $pdo->query("SELECT * FROM {$quoted}");

        $columns = null;
        $batch = [];
        $batchSize = 500;
        $wroteHeader = false;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if (!$wroteHeader) {
                fwrite($handle, "--\n-- Data for {$quoted}\n--\n\n");
                $wroteHeader = true;
            }

            if ($columns === null) {
                $columns = array_map(fn ($c) => $this->quoteIdentifier($c), array_keys($row));
            }

            $values = array_map(
                fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($row)
            );

            $batch[] = '(' . implode(',', $values) . ')';

            if (count($batch) >= $batchSize) {
                $this->flushInsertBatch($handle, $quoted, $columns, $batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->flushInsertBatch($handle, $quoted, $columns, $batch);
        }

        if ($wroteHeader) {
            fwrite($handle, "\n");
        }
    }

    private function flushInsertBatch($handle, string $quotedTable, array $columns, array $batch): void
    {
        fwrite($handle, "INSERT INTO {$quotedTable} (" . implode(',', $columns) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    private function dumpViewSchema(PDO $pdo, string $viewName, $handle): void
    {
        $quoted = $this->quoteIdentifier($viewName);

        $createSql = $pdo->query("SHOW CREATE VIEW {$quoted}")->fetch(PDO::FETCH_ASSOC)['Create View'] ?? null;

        if (!$createSql) {
            return;
        }

        fwrite($handle, "\n--\n-- View structure for {$quoted}\n--\n\n");
        fwrite($handle, "DROP VIEW IF EXISTS {$quoted};\n");
        fwrite($handle, $createSql . ";\n\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /*
    |--------------------------------------------------------------------------
    | LIST LOCAL BACKUPS
    |--------------------------------------------------------------------------
    */

    public function list()
    {
        $this->ensureAdmin();

        $files = collect(File::files($this->backupDir()))
            ->filter(fn ($file) => $file->getExtension() === 'sql')
            ->map(fn ($file) => [
                'file_name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('d-m-Y H:i:s', $file->getMTime()),
                'created_ts' => $file->getMTime(),
            ])
            ->sortByDesc('created_ts')
            ->values();

        return response()->json([
            'status' => true,
            'data' => $files,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    public function download(string $filename)
    {
        $this->ensureAdmin();

        if (!preg_match('/^cloud-backup-[A-Za-z0-9_\-]+\.sql$/', $filename)) {
            abort(404);
        }

        $filePath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;

        if (!File::exists($filePath)) {
            abort(404);
        }

        return response()->download($filePath);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(string $filename, AuditService $auditService)
    {
        $this->ensureAdmin();

        if (!preg_match('/^cloud-backup-[A-Za-z0-9_\-]+\.sql$/', $filename)) {
            abort(404);
        }

        $filePath = $this->backupDir() . DIRECTORY_SEPARATOR . $filename;

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        // No BackupLog row is guaranteed to exist for this file (e.g. backups
        // taken before this table existed), so ensure one to log the delete
        // against rather than skipping the audit entry.
        $log = BackupLog::firstOrCreate(
            ['file_name' => $filename],
            ['status' => 'SUCCESS', 'created_by' => Auth::id()]
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $log,
            AuditLog::ACTION_DELETE,
            'Backup file deleted: ' . $filename
        );

        return response()->json([
            'status' => true,
            'message' => 'Backup file deleted.',
        ]);
    }
}
