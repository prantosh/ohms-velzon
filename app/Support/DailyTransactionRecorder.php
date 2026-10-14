<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Replaces the old COUNT(*)+1 transaction_no generation used at every
// daily_transactions call site -- that broke both under concurrency
// (two requests reading the same count) and after any row deletion
// (count() permanently falls behind the highest id/serial ever issued,
// which is what caused the 1062 duplicate-key crash). Deriving the
// serial from the row's own auto-increment id is immune to both.
class DailyTransactionRecorder
{
    public static function insert(array $data): string
    {
        // transaction_no is NOT NULL + UNIQUE with no default; a UUID
        // placeholder can't collide and gets overwritten below.
        $data['transaction_no'] = (string) Str::uuid();

        $id = DB::table('daily_transactions')->insertGetId($data);

        $transactionNo = OfflineMode::prefix('TRN/')
            . now()->format('ymd')
            . '/'
            . str_pad((string) $id, 6, '0', STR_PAD_LEFT);

        DB::table('daily_transactions')
            ->where('id', $id)
            ->update(['transaction_no' => $transactionNo]);

        return $transactionNo;
    }
}
