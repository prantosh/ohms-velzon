<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathologyReportTemplateItem extends Model
{
    protected $fillable = [

        'pathology_report_template_id',
        'item_code_sub',
    ];

    public function template()
    {
        return $this->belongsTo(PathologyReportTemplate::class, 'pathology_report_template_id');
    }
}
