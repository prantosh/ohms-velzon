<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathologyReportFinding extends Model
{
    protected $fillable = [

        'invoice_no',
        'pathology_report_template_id',
        'content',
        'report_title',

        'confirmed_by',
        'confirmed_at',
        'corrected_from_sent_at',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'corrected_from_sent_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PathologyReportFindingItem::class, 'pathology_report_finding_id');
    }

    public function template()
    {
        return $this->belongsTo(PathologyReportTemplate::class, 'pathology_report_template_id');
    }
}
