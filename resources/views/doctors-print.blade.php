<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        @page{
            size:A4 portrait;
            margin:5mm;
        }

        body{

            font-family:Arial, Helvetica, sans-serif;
            background:#fff;
            color:#000;

            font-size:11px;
        }

        .report-container{

            width:100%;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header{

            display:flex;
            align-items:center;
            justify-content:center;

            gap:10px;

            padding:5px 0 8px 0;

            border-bottom:1px solid #0d6efd;

            margin-bottom:8px;
        }

        .report-logo{

            width:75px;
            height:75px;

            object-fit:contain;
        }
        .text-end{

        text-align:right;
        }
        .title-section h1{

            font-size:22px;

            color:#0d6efd;

            margin:0;

            line-height:1.2;

            font-weight:700;
        }

        /*
        |--------------------------------------------------------------------------
        | REPORT TITLE
        |--------------------------------------------------------------------------
        */

        .report-title{

            text-align:center;

            margin-bottom:4px;
        }

        .report-title h5{

            font-size:16px;

            margin:0;

            color:#000;
        }

        /*
        |--------------------------------------------------------------------------
        | REPORT INFO
        |--------------------------------------------------------------------------
        */

        .report-info{

            display:flex;

            justify-content:space-between;

            margin-bottom:8px;

            font-size:11px;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        table{

            width:100%;

            border-collapse:collapse;
        }

       thead{

            background:#ffffff;

            color:#000000;
        }

        th{

            padding:7px;

            font-size:11px;

            font-weight:bold;

            text-align:left;

            border:1px solid #000000;

            color:#000000;

            background:#ffffff;

            letter-spacing:0.5px;
        }

        td{

            padding:6px;

            font-size:10px;

            border:1px solid #dcdcdc;
        }

        tbody tr:nth-child(even){

            background:#f8f9fa;
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status-active{

            color:green;

            font-weight:bold;
        }

        .status-block{

            color:red;

            font-weight:bold;
        }

        /*
        |--------------------------------------------------------------------------
        | SIGNATURE
        |--------------------------------------------------------------------------
        */

        .signature{

            margin-top:25px;

            text-align:right;
        }

        .signature p{

            display:inline-block;

            min-width:160px;

            border-top:1px solid #000;

            padding-top:4px;

            font-size:11px;

            text-align:center;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer{

            margin-top:10px;

            display:flex;

            justify-content:space-between;

            font-size:10px;

            color:#666;
        }

        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        @media print{

            body{

                margin:0;
            }
        }

    </style>

</head>

<body>

    <div class="report-container">

        <!-- HEADER -->

        <div class="header">

            <div class="logo-section">

                <img src="{{ asset('build/images/logo.png') }}"
                     alt="Logo"
                     class="report-logo">

            </div>

            <div class="title-section">

                <h1>
                    Dr. Amitava Basu Smriti Swastha Raksha Kendra
                </h1>

            </div>

        </div>

        <!-- REPORT TITLE -->

        <div class="report-title">

            <h5>Doctor List</h5>

        </div>

        <!-- REPORT INFO -->

        <div class="report-info">

            <div>
                <strong>Total Doctors :</strong>
                {{ count($doctors) }}
            </div>

            <div>
                <strong>Generated Date :</strong>
                {{ date('d-m-Y') }}
            </div>

            <div>
                <strong>Generated Time :</strong>
                {{ date('h:i A') }}
            </div>

        </div>

        <!-- TABLE -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Code</th>
                        <th>Name</th>
                        <th>Registration No.</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Joining Date</th>
                        <th>Specialisation</th>
                        <th>Experience</th>
                        <th>Total Fees</th>
                        <th>Doctor Fees</th>
                        <th>Doctor Fees(Disc)</th>
                        <th>Discount</th>
                        
                        <th>Status</th>

                    </tr>

                </thead>

                <tbody>

                    @foreach($doctors as $row)

                        <tr>

                            <td>
                                #DOC{{ $row->doctor_code }}
                            </td>

                            <td>
                                {{ $row->doctor_name }}
                            </td>
                            <td>
                                {{ $row->registration_no }}
                            </td>
                            <td class="text-end">

                                {{ $row->mobile_no }}
                            </td>
                            <td>
                                {{ $row->email }}
                            </td>
                            <td>
                                {{ $row->joining_date }}
                            </td>
                            <td class="text-end">

                                {{ $row->specialisation }}
                            </td>
                            <td class="text-end">

                                {{ $row->experience_years }}
                            </td>
                            <td class="text-end">

                                {{ $row->consultation_fee_total }}
                            </td>
                            <td class="text-end">

                                {{ $row->consultation_fee_doctor }}
                            </td>
                            <td class="text-end">

                                {{ $row->consultation_fee_doctor_discounted_patient }}
                            </td>
                            <td class="text-end">

                                {{ $row->discount }}
                            </td>
                            <td>

                                @if($row->status == 'Active')

                                    <span class="status-active">
                                        Active
                                    </span>

                                @else

                                    <span class="status-block">
                                        Block
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

        <!-- SIGNATURE -->

        <div class="signature">

            <p>
                Authorized Signature
            </p>

        </div>

        <!-- FOOTER -->

        <div class="footer">

            <div>
                Generated by IT Team, ABSSRK
            </div>

            <div>
                © {{ date('Y') }} ABSSRK
            </div>

        </div>

    </div>

    <script>

    window.onload = function () {

        document.title = '';

        window.print();
    };

</script>

</body>

</html>
