<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\File;
use ZipArchive;

class InvoicesBackupService
{
    /*
    |--------------------------------------------------------------------------
    | INVOICES FOLDER BACKUP
    |--------------------------------------------------------------------------
    | public/invoices holds every invoice/report PDF ever generated, but it's
    | just a side effect of the WhatsApp-send flow (WATI needs a fetchable
    | URL) -- nothing in the app reads these files back, every print/reprint
    | action regenerates fresh from the database instead. That means the only
    | reason to keep them is to preserve the exact document that was actually
    | sent/printed at the time, in case the underlying data changes later.
    | This zips the folder on a schedule so an Admin can periodically pull a
    | copy off the server via the Backups screen, matching the CloudBackup
    | (database) flow already used for the same purpose on this
    | no-SSH GoDaddy host.
    */

    // Keep this many of the most recent invoices-backup-*.zip files on the
    // server; older ones are pruned automatically after each new backup so
    // the backups folder itself doesn't grow unbounded. This is a rolling
    // safety window, not the long-term archive -- the Admin is expected to
    // download copies off the server periodically for that.
    public const RETENTION_COUNT = 8;

    public function backupDir(): string
    {
        $dir = storage_path('app/backups');

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    public function run(?int $createdBy = null): array
    {
        $sourceDir = public_path('invoices');

        $files = File::isDirectory($sourceDir) ? File::files($sourceDir) : [];

        $fileName = 'invoices-backup-' . now()->format('Y-m-d_His') . '.zip';
        $zipPath = $this->backupDir() . DIRECTORY_SEPARATOR . $fileName;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

            BackupLog::create([
                'file_name' => $fileName,
                'status' => 'FAILED',
                'message' => 'Could not create zip archive.',
                'created_by' => $createdBy,
            ]);

            return ['status' => false, 'message' => 'Could not create zip archive.'];
        }

        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $fileCount = count($files);

        $zip->close();

        if (!File::exists($zipPath) || File::size($zipPath) === 0) {

            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            BackupLog::create([
                'file_name' => $fileName,
                'status' => 'FAILED',
                'message' => 'Backup produced an empty/corrupt archive.',
                'created_by' => $createdBy,
            ]);

            return ['status' => false, 'message' => 'Backup produced an empty/corrupt archive.'];
        }

        BackupLog::create([
            'file_name' => $fileName,
            'size_bytes' => File::size($zipPath),
            'status' => 'SUCCESS',
            'message' => $fileCount . ' file(s) archived.',
            'created_by' => $createdBy,
        ]);

        $this->pruneOldBackups();

        return [
            'status' => true,
            'file_name' => $fileName,
            'size' => File::size($zipPath),
            'file_count' => $fileCount,
        ];
    }

    public function list()
    {
        return collect(File::files($this->backupDir()))
            ->filter(fn ($file) => preg_match('/^invoices-backup-.*\.zip$/', $file->getFilename()))
            ->map(fn ($file) => [
                'file_name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('d-m-Y H:i:s', $file->getMTime()),
                'created_ts' => $file->getMTime(),
            ])
            ->sortByDesc('created_ts')
            ->values();
    }

    private function pruneOldBackups(): void
    {
        $files = collect(File::files($this->backupDir()))
            ->filter(fn ($file) => preg_match('/^invoices-backup-.*\.zip$/', $file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($files->slice(self::RETENTION_COUNT) as $old) {
            File::delete($old->getPathname());
        }
    }
}
