<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="index" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ URL::asset('build/images/logo-sm.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ URL::asset('build/images/logo-dark.png') }}" alt="" height="17">
            </span>
        </a>
        <!-- Light Logo-->
        <a href="index" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ URL::asset('build/images/logo-sm.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ URL::asset('build/images/logo-light.png') }}" alt="" height="17">
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">

            <div id="two-column-menu">
            </div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title"><span>@lang('translation.menu')</span></li>
                <li class="nav-item">
                    <a class="nav-link menu-link" href="{{ route('root') }}">
                        <i class="ri-home-5-line"></i>
                        <span>Home</span>
                    </a>
                </li> <!-- end Dashboard Menu -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-settings-4-line"></i> <span>@lang('translation.admin')</span><span
                            class="badge badge-pill bg-danger">@lang('translation.hot')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                           
                            @if(is_null($allowedPages) || in_array('cancellation-permission', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('cancellation-permission.index') }}" class="nav-link">@lang('translation.cancellationpermission')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('activity-log', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('activity-log.index') }}" class="nav-link">@lang('translation.activitylog')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('audit-logs', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('audit-logs.index') }}" class="nav-link">@lang('translation.auditlogs')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('membership-fee-status', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('membership-fee-status.index') }}" class="nav-link">@lang('translation.membershipfeestatus')</a>
                                        </li>
                            @endif
                            
                            @if(is_null($allowedPages) || in_array('cloud-backup', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('cloud-backup.index') }}" class="nav-link">@lang('translation.cloudbackup')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('maintenance-mode', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('maintenance-mode.index') }}" class="nav-link">@lang('translation.maintenancemode')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('whatsapp-auto-send-settings', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('whatsapp-auto-send-settings.index') }}" class="nav-link">@lang('translation.whatsappautosendsettings')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('role-page-access', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('role-page-access.index') }}" class="nav-link">@lang('translation.rolepageaccess')</a>
                                        </li>
                            @endif

                        </ul>
                    </div>
                </li> <!-- end Dashboard Menu -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarUsers" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarUsers">
                        <i class="ri-database-2-line"></i> <span>@lang('translation.mastermanagement')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarUsers">
                        <ul class="nav nav-sm flex-column">
                            @if(is_null($allowedPages) || in_array('users', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('users.index') }}" class="nav-link">@lang('translation.usermaster')</a>
                                        </li>
                            @endif

                            <li class="nav-item">
                                <a href="#sidebarSignIn" class="nav-link" data-bs-toggle="collapse" role="button"
                                    aria-expanded="false" aria-controls="sidebarSignIn">@lang('translation.doctor')
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarSignIn">
                                    <ul class="nav nav-sm flex-column">
                                        @if(is_null($allowedPages) || in_array('doctor-master', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('doctors.index') }}" class="nav-link">@lang('translation.doctormaster')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('doctor-specialisation', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('specialisation.index') }}" class="nav-link">@lang('translation.doctorspecialisation')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('doctor-test-payment-master', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('doctor-test-payment-master.index') }}" class="nav-link">@lang('translation.doctortestpaymentmaster')</a>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                            </li>
                            <li class="nav-item">
                                <a href="#sidebarSignIn" class="nav-link" data-bs-toggle="collapse" role="button"
                                    aria-expanded="false" aria-controls="sidebarSignIn">@lang('translation.diagnostictest')
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarSignIn">
                                    <ul class="nav nav-sm flex-column">
                                         @if(is_null($allowedPages) || in_array('test-category-master', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('invoice-item-masters.index') }}" class="nav-link">@lang('translation.testcategorymaster')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('test-detail-master', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('invoice-item-details.index') }}" class="nav-link">@lang('translation.testdetailmaster')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('test-group-master', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('test-group-master.index') }}" class="nav-link">@lang('translation.testgroupmaster')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('usg-report-template', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('usg-report-template.index') }}" class="nav-link">@lang('translation.usgreporttemplate')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('test-report-template', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('test-report-template.index') }}" class="nav-link">@lang('translation.testreporttemplate')</a>
                                        </li>
                                        @endif

                                        @if(is_null($allowedPages) || in_array('test-parameter', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('test-parameter.index') }}" class="nav-link">@lang('translation.testparametermaster')</a>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                            </li>
                            @if(is_null($allowedPages) || collect([
                                'test-extra-field-type', 'instrument-master', 'kit-master',
                                'note-master', 'microscopy-master', 'impression-master',
                                'detail-range-master', 'sample-master', 'uom-master', 'remarks-master',
                            ])->intersect($allowedPages)->isNotEmpty())
                            <li class="nav-item">
                                <a href="{{ route('diagnostic-test-additional-info.index') }}" class="nav-link">@lang('translation.diagnostictestadditionalinfo')</a>
                            </li>
                            @endif
                            
                            @if(is_null($allowedPages) || in_array('equipment-category', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('equipment-category.index') }}" class="nav-link">@lang('translation.equipmentcategorymaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('ambulance-destination', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('ambulance-destination.index') }}" class="nav-link">@lang('translation.ambulancedestinationmaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('membership-fee-rate', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('membership-fee-rate.index') }}" class="nav-link">@lang('translation.membershipfeeratemaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('patient-card', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('patient-card.index') }}" class="nav-link">@lang('translation.patientcardmaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('reporting-group', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('reporting-group.index') }}" class="nav-link">@lang('translation.reportinggroupmaster')</a>
                                        </li>
                            @endif
                            <li class="nav-item">
                                <a href="#sidebarSignIn" class="nav-link" data-bs-toggle="collapse" role="button"
                                    aria-expanded="false" aria-controls="sidebarSignIn">@lang('translation.expenditure')
                                </a>
                                <div class="collapse menu-dropdown" id="sidebarSignIn">
                                    <ul class="nav nav-sm flex-column">
                                         @if(is_null($allowedPages) || in_array('expenditure-category', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('expenditure-category.index') }}" class="nav-link">@lang('translation.expenditurecategorymaster')</a>
                                        </li>
                                        @endif
                                         @if(is_null($allowedPages) || in_array('expenditure-agency', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('expenditure-agency.index') }}" class="nav-link">@lang('translation.expenditureagencymaster')</a>
                                        </li>
                                        @endif
                                        @if(is_null($allowedPages) || in_array('income-category', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('income-category.index') }}" class="nav-link">@lang('translation.incomecategorymaster')</a>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                            </li>
                            
                        </ul>
                    </div>
                </li> <!-- end Users Menu -->

                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-calendar-check-line"></i> <span>@lang('translation.appointment')</span><span
                            class="badge badge-pill bg-danger">@lang('translation.hot')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                            @if(is_null($allowedPages) || in_array('doctor-appointments', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-appointments.index') }}" class="nav-link">@lang('translation.doctorappointments')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-appointment-calendar', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-schedule.calendar') }}" class="nav-link">@lang('translation.doctorcalender')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-consultation-list', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-consultation-list.index') }}" class="nav-link">@lang('translation.doctorconsultationlist')</a>
                                        </li>
                            @endif
                        </ul>
                    </div>
                </li> <!-- end Dashboard Menu -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-stethoscope-line"></i> <span>@lang('translation.doctor')</span><span
                            class="badge badge-pill bg-danger">@lang('translation.hot')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                            
                            @if(is_null($allowedPages) || in_array('doctor-schedules', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-schedules.index') }}" class="nav-link">@lang('translation.doctorschedules')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-history', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-history.index') }}" class="nav-link">@lang('translation.doctorhistory')</a>
                                        </li>
                            @endif
                            
                            @if(is_null($allowedPages) || in_array('doctor-payable', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-payable.index') }}" class="nav-link">@lang('translation.doctorpayablestatusall')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-payable-by-user', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-payable-by-user.index') }}" class="nav-link">@lang('translation.doctorpayablebyuser')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-settlement', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-settlement.index') }}" class="nav-link">@lang('translation.doctorsettlement')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-settlement-by-user', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-settlement-by-user.index') }}" class="nav-link">@lang('translation.doctorsettlementbyuser')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-visit-payment-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-visit-payment-report.index') }}" class="nav-link">@lang('translation.doctorvisitpaymentreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-test-payment-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-test-payment-report.index') }}" class="nav-link">@lang('translation.doctortestpaymentreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-payment-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-payment-report.index') }}" class="nav-link">@lang('translation.doctorpaymentreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-payable-non-cash-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-payable-non-cash-report.index') }}" class="nav-link">@lang('translation.doctorpayablenoncashreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('doctor-wise-payment-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('doctor-wise-payment-report.index') }}" class="nav-link">@lang('translation.doctorwisepaymentreport')</a>
                                        </li>
                            @endif
                        </ul>
                    </div>
                </li> <!-- end Dashboard Menu -->
                
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-flask-line"></i> <span>@lang('translation.diagnostictest')</span><span
                            class="badge badge-pill bg-danger">@lang('translation.hot')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                           
                           
                            @if(is_null($allowedPages) || in_array('test-result-entry', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('test-result-entry.index') }}" class="nav-link">@lang('translation.testresultentry')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('usg-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('usg-report.index') }}" class="nav-link">@lang('translation.usgreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('diagnostic-test-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('diagnostic-test-report.index') }}" class="nav-link">@lang('translation.diagnostictestreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('test-report-dashboard', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('test-report-dashboard.index') }}" class="nav-link">@lang('translation.testreportdashboard')</a>
                                        </li>
                            @endif

                        </ul>
                    </div>
                </li> <!-- end Dashboard Menu -->

                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-bill-line"></i> <span>@lang('translation.billing')</span><span
                            class="badge badge-pill bg-danger">@lang('translation.hot')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                            @if(is_null($allowedPages) || in_array('doctor-visit-invoice', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('doctor-visit-invoices.index') }}" class="nav-link">@lang('translation.doctorvisitinvoices')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('diagnostic-invoice', $allowedPages))
                                        <li class="nav-item">
                                        <a href="{{ route('diagnostic-invoice.index') }}" class="nav-link">@lang('translation.diagnosticinvoices')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('equipment-rental', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('equipment-rental.index') }}" class="nav-link">@lang('translation.equipmentrenttransaction')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('ambulance-rental', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('ambulance-rental.index') }}" class="nav-link">@lang('translation.ambulancerentalinvoice')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('membership-fee', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('membership-fee.index') }}" class="nav-link">@lang('translation.membershipfeecollection')</a>
                                        </li>
                            @endif
                            
                            
                            @if(is_null($allowedPages) || in_array('expenditure', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('expenditure.index') }}" class="nav-link">@lang('translation.expenditureentry')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('income', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('income.index') }}" class="nav-link">@lang('translation.incomeentry')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('invoice-cancellation', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('invoice-cancellation.index') }}" class="nav-link">@lang('translation.invoicecancellation')</a>
                                        </li>
                            @endif
                        </ul>
                    </div>
                </li> <!-- end Dashboard Menu -->

                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarLayouts" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarLayouts">
                        <i class="ri-archive-2-line"></i> <span>@lang('translation.inventory')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarLayouts">
                        <ul class="nav nav-sm flex-column">
                            @if(is_null($allowedPages) || in_array('inventory-category', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('inventory-category.index') }}" class="nav-link">@lang('translation.inventorycategorymaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('inventory-item', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('inventory-item.index') }}" class="nav-link">@lang('translation.inventoryitemmaster')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('purchase-order', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('purchase-order.index') }}" class="nav-link">@lang('translation.purchaseorder')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('goods-receipt', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('goods-receipt.index') }}" class="nav-link">@lang('translation.goodsreceipt')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('stock-issue', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('stock-issue.index') }}" class="nav-link">@lang('translation.stockissue')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('stock-ledger', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('stock-ledger.index') }}" class="nav-link">@lang('translation.stockledger')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('purchase-order-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('purchase-order-report.index') }}" class="nav-link">@lang('translation.purchaseorderreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('goods-receipt-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('goods-receipt-report.index') }}" class="nav-link">@lang('translation.goodsreceiptreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('goods-issue-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('goods-issue-report.index') }}" class="nav-link">@lang('translation.goodsissuereport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('stock-as-on-date', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('stock-as-on-date.index') }}" class="nav-link">@lang('translation.stockasondate')</a>
                                        </li>
                            @endif
                        </ul>
                    </div>
                </li> <!-- end Inventory Menu -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarUsers" data-bs-toggle="collapse" role="button"
                        aria-expanded="false" aria-controls="sidebarUsers">
                        <i class="ri-bar-chart-2-line"></i> <span>@lang('translation.report')</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarUsers">
                        <ul class="nav nav-sm flex-column">
                            @if(is_null($allowedPages) || in_array('patient-history', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('patient-history.index') }}" class="nav-link">@lang('translation.patienthistory')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('cash-submission-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('cash-submission-report.index') }}" class="nav-link">@lang('translation.cashsubmissionreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('employee-performance', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('employee-performance.index') }}" class="nav-link">@lang('translation.employeeperformance')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('daily-transaction-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('daily-transaction-report.index') }}" class="nav-link">@lang('translation.dailytransactionreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('whatsapp-message-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('whatsapp-message-report.index') }}" class="nav-link">@lang('translation.whatsappmessagereport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('item-wise-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('item-wise-report.index') }}" class="nav-link">@lang('translation.itemwisereport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('item-wise-summary-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('item-wise-summary-report.index') }}" class="nav-link">@lang('translation.itemwisesummaryreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('monthly-reconciliation-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('monthly-reconciliation-report.index') }}" class="nav-link">@lang('translation.monthlyreconciliationreport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('user-invoice-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('user-invoice-report.index') }}" class="nav-link">@lang('translation.userinvoicereport')</a>
                                        </li>
                            @endif
                            @if(is_null($allowedPages) || in_array('all-invoices-report', $allowedPages))
                            <li class="nav-item">
                                        <a href="{{ route('all-invoices-report.index') }}" class="nav-link">@lang('translation.allinvoicesreport')</a>
                                        </li>
                            @endif
                            
                                                     
                            

                        </ul>
                    </div>
                </li> <!-- end Users Menu -->
                <li class="menu-title"><i class="ri-more-fill"></i> <span>@lang('translation.pages')</span></li>


            </ul>
        </div>
        <!-- Sidebar -->
    </div>
    <div class="sidebar-background"></div>
</div>
<!-- Left Sidebar End -->
<!-- Vertical Overlay-->
<div class="vertical-overlay"></div>
