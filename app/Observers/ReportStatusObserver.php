<?php

namespace App\Observers;

use App\Models\PathologyReportFinding;
use App\Models\PathologyReportFindingItem;
use App\Services\InvoiceReportStatusRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Refreshes the stored report status on the invoice (see
 * InvoiceReportStatusRecorder) whenever something that can change it is
 * written: a report row is created / confirmed / removed, a Pathology report
 * claims or releases a billed line, the legacy whole-invoice confirmation
 * is set, or an X-Ray PDF is uploaded. Plain content edits of an existing
 * report don't change the status and are skipped, so saving report text
 * costs nothing extra.
 *
 * Registered for the report models in AppServiceProvider::boot().
 */
class ReportStatusObserver
{
    public function saved(Model $model): void
    {
        // Status only turns on a report existing and on its confirmation.
        if (!$model->wasRecentlyCreated && !$this->confirmationChanged($model)) {
            return;
        }

        $this->refresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    private function confirmationChanged(Model $model): bool
    {
        // Models without a confirmed_at (legacy confirmation row, X-Ray
        // upload) change status on any save: re-upload / re-confirm.
        return !array_key_exists('confirmed_at', $model->getAttributes())
            || $model->wasChanged('confirmed_at');
    }

    private function refresh(Model $model): void
    {
        $invoiceNo = $model instanceof PathologyReportFindingItem
            ? optional(PathologyReportFinding::find($model->pathology_report_finding_id))->invoice_no
            : ($model->invoice_no ?? null);

        app(InvoiceReportStatusRecorder::class)->refreshByInvoiceNo($invoiceNo);
    }
}
