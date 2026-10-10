<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\XrayReportUpload;
use Carbon\Carbon;
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
    // download copies off the server periodically for that. Set to 10
    // (~10 weeks) rather than exactly matching PRUNE_ORIGINALS_AFTER_DAYS so
    // a zip covering a file doesn't get rotated off disk in the same week
    // that file becomes eligible for original-deletion -- see pruneOriginals().
    public const RETENTION_COUNT = 30;

    // Originals in public/invoices older than this are deleted by
    // pruneOriginals(), but ONLY once a successful backup already covers
    // them -- see the safety check there. This is what keeps the live
    // folder itself from growing forever; RETENTION_COUNT above is a
    // separate, smaller cap on the backup zips themselves.
    public const PRUNE_ORIGINALS_AFTER_DAYS = 60;

    // Archive-and-clear (see archiveAndPrune()): fixed 3-calendar-day
    // blocks. On every run day (ARCHIVE_ANCHOR + a multiple of 3 days) the
    // folder holds the last 6 days; the oldest 3 days are zipped into
    // invoices-backup-<ddmmyy>_<ddmmyy>.zip and removed, leaving the latest
    // 3 days. E.g. on 06/10 the folder has 01/10-06/10, so 01/10-03/10 is
    // archived and 04/10-06/10 stays; on 09/10, 04/10-06/10 goes next.
    // 30 zips x 3 days is roughly 90 days of history kept on the server.
    public const ARCHIVE_ANCHOR = '2026-10-06';

    public const ARCHIVE_BLOCK_DAYS = 3;

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

    /**
     * On each run day of the 3-day grid, zips every file in public/invoices
     * dated on or before runDay-3 into one archive, verifies it, and only
     * then deletes exactly those files from the live folder. Safe to call
     * daily (see $runDay below). Any failure leaves the originals alone.
     */
    public function archiveAndPrune(bool $force = false, ?int $createdBy = null): array
    {
        $sourceDir = public_path('invoices');

        if (!File::isDirectory($sourceDir)) {
            return ['status' => true, 'skipped' => 'no invoices folder'];
        }

        // The most recent run day on the 3-day grid (today, or 1-2 days
        // back if today isn't one). Because this is derived from the date
        // alone, calling daily is idempotent, and if the cron missed the
        // run day the next call still does that same run.
        $today = now()->startOfDay();
        $sinceAnchor = Carbon::parse(self::ARCHIVE_ANCHOR)->startOfDay()->diffInDays($today, false);

        $runDay = $force
            ? $today
            : $today->copy()->subDays((($sinceAnchor % self::ARCHIVE_BLOCK_DAYS) + self::ARCHIVE_BLOCK_DAYS) % self::ARCHIVE_BLOCK_DAYS);

        // Keep the latest ARCHIVE_BLOCK_DAYS days (runDay-2..runDay);
        // everything dated on or before runDay-3 is archived.
        $lastArchivedDay = $runDay->copy()->subDays(self::ARCHIVE_BLOCK_DAYS);
        $cutoff = $lastArchivedDay->copy()->endOfDay()->timestamp;

        $files = collect(File::files($sourceDir))
            // Uploaded X-Ray report PDFs are the live copy the dashboards
            // link to, not a throwaway side effect of a WhatsApp send --
            // never archive them away or those links would 404.
            ->reject(fn ($file) => str_ends_with($file->getFilename(), XrayReportUpload::FILE_SUFFIX))
            ->filter(fn ($file) => $file->getMTime() <= $cutoff)
            ->sortBy(fn ($file) => $file->getMTime())
            ->values();

        if ($files->isEmpty()) {
            return ['status' => true, 'skipped' => 'nothing dated on or before ' . $lastArchivedDay->format('d-m-Y')];
        }

        // A normal block is labelled with its full 3 days even if some
        // had no files; a first/catch-up run reaching further back is
        // labelled from its oldest file.
        $firstDay = Carbon::createFromTimestamp($files->first()->getMTime())->startOfDay();
        $blockStart = $lastArchivedDay->copy()->subDays(self::ARCHIVE_BLOCK_DAYS - 1);

        if ($firstDay->greaterThan($blockStart)) {
            $firstDay = $blockStart;
        }

        $from = $firstDay->format('dmy');
        $to = $lastArchivedDay->format('dmy');
        $range = $firstDay->format('d-m-Y') . ' to ' . $lastArchivedDay->format('d-m-Y');

        $fileName = "invoices-backup-{$from}_{$to}.zip";

        if (File::exists($this->backupDir() . DIRECTORY_SEPARATOR . $fileName)) {
            $fileName = "invoices-backup-{$from}_{$to}_" . now()->format('His') . '.zip';
        }

        $zipPath = $this->backupDir() . DIRECTORY_SEPARATOR . $fileName;

        $fail = function (string $message) use ($fileName, $zipPath, $createdBy) {

            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            BackupLog::create([
                'file_name' => $fileName,
                'status' => 'FAILED',
                'message' => $message,
                'created_by' => $createdBy,
            ]);

            return ['status' => false, 'message' => $message];
        };

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $fail('Could not create zip archive.');
        }

        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();

        // Re-open and count before trusting the archive enough to delete
        // anything.
        $check = new ZipArchive();

        if ($check->open($zipPath) !== true || $check->numFiles !== $files->count()) {

            $check->close();

            return $fail('Archive verification failed; no files were removed.');
        }

        $check->close();

        foreach ($files as $file) {
            File::delete($file->getPathname());
        }

        BackupLog::create([
            'file_name' => $fileName,
            'size_bytes' => File::size($zipPath),
            'status' => 'SUCCESS',
            'message' => $files->count() . ' file(s) archived (' . $range . ') and removed from the live folder.',
            'created_by' => $createdBy,
        ]);

        $this->pruneOldBackups();

        return [
            'status' => true,
            'file_name' => $fileName,
            'file_count' => $files->count(),
            'size' => File::size($zipPath),
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

    /*
    |--------------------------------------------------------------------------
    | PRUNE ORIGINALS
    |--------------------------------------------------------------------------
    | Deletes files from public/invoices itself once they're old enough AND a
    | successful backup already ran after they were created -- each backup
    | zips the entire folder as it stands at that moment, so a SUCCESS log
    | dated after a file's mtime proves that file was captured in some zip,
    | even if that particular zip has since rotated out of RETENTION_COUNT.
    | A file with no such log yet (e.g. backups have been failing) is left
    | alone no matter how old it is, so a broken backup job can never lead to
    | silently losing an original that was never actually backed up.
    */

    public function pruneOriginals(int $days = self::PRUNE_ORIGINALS_AFTER_DAYS): array
    {
        $sourceDir = public_path('invoices');

        if (!File::isDirectory($sourceDir)) {
            return ['status' => true, 'deleted' => 0, 'skipped' => 0];
        }

        $cutoff = now()->subDays($days)->timestamp;

        $deleted = 0;
        $skipped = 0;

        foreach (File::files($sourceDir) as $file) {

            if ($file->getMTime() > $cutoff) {
                continue;
            }

            // Uploaded X-Ray reports are live files, never pruned.
            if (str_ends_with($file->getFilename(), XrayReportUpload::FILE_SUFFIX)) {
                continue;
            }

            $backedUpSince = BackupLog::where('status', 'SUCCESS')
                ->where('file_name', 'like', 'invoices-backup-%')
                ->where('created_at', '>=', date('Y-m-d H:i:s', $file->getMTime()))
                ->exists();

            if (!$backedUpSince) {
                $skipped++;
                continue;
            }

            File::delete($file->getPathname());
            $deleted++;
        }

        return ['status' => true, 'deleted' => $deleted, 'skipped' => $skipped];
    }
}
