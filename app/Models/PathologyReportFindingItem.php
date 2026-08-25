<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathologyReportFindingItem extends Model
{
    protected $fillable = [

        'pathology_report_finding_id',
        'invoice_detail_id',
        'item_code_sub',
    ];

    public function finding()
    {
        return $this->belongsTo(PathologyReportFinding::class, 'pathology_report_finding_id');
    }
}
