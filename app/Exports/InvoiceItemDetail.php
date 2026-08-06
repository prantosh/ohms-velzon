<?php

namespace App\Exports;

use App\Models\InvoiceItemDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InvoiceItemDetailExport implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize
{

    public function collection()
    {

        return InvoiceItemDetail::leftJoin(
            'invoice_item_masters',
            'invoice_item_details.item_code',
            '=',
            'invoice_item_masters.item_code'
        )
            ->select(

                'invoice_item_details.item_code',

                'invoice_item_masters.item_name',

                'invoice_item_masters.item_type',

                'invoice_item_details.item_code_sub',

                'invoice_item_details.item_description_sub',

                'invoice_item_details.rate',

                'invoice_item_details.standard_discount',

                'invoice_item_details.member_discount',

                'invoice_item_details.other_lab_discount',

                'invoice_item_details.member_other_lab_discount',

                'invoice_item_details.test_group',

                'invoice_item_details.uom',

                'invoice_item_details.male_range',

                'invoice_item_details.female_range',

                'invoice_item_details.common_range',

                'invoice_item_details.method',

                'invoice_item_details.report_days',

                'invoice_item_details.status',

                'invoice_item_details.created_at',

                'invoice_item_details.updated_at'

            )

            ->orderBy(
                'invoice_item_details.item_code'
            )

            ->orderBy(
                'invoice_item_details.item_code_sub'
            )

            ->get();

    }

    public function headings(): array
    {

        return [

            'Item Code',

            'Item Name',

            'Item Type',

            'Sub Item Code',

            'Description',

            'Rate',

            'Standard Discount',

            'Member Discount',

            'Other Lab Discount',

            'Member Other Lab Discount',

            'Test Group',

            'UOM',

            'Male Range',

            'Female Range',

            'Common Range',

            'Method',

            'Report Days',

            'Status',

            'Created',

            'Updated'

        ];

    }

}
