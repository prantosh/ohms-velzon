<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestReportTemplateValue extends Model
{
    protected $fillable = [

        'test_report_template_id',

        'test_extra_field_type_id',

        'value',
    ];

    public function template()
    {
        return $this->belongsTo(TestReportTemplate::class, 'test_report_template_id');
    }

    public function fieldType()
    {
        return $this->belongsTo(TestExtraFieldType::class, 'test_extra_field_type_id');
    }
}
