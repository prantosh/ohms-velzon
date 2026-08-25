<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PathologyReportTemplate extends Model
{
    protected $fillable = [

        'title',
        'test_group_code',
        'content',
        'status',

        'created_by',
        'updated_by',
    ];

    public function items()
    {
        return $this->hasMany(PathologyReportTemplateItem::class, 'pathology_report_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
