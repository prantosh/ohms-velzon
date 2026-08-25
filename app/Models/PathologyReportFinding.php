<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathologyReportFinding extends Model
{
    protected $fillable = [

        'invoice_no',
        'pathology_report_template_id',
        'content',

        'confirmed_by',
        'confirmed_at',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
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
