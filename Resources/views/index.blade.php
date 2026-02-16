@extends('layouts.app')
@section('title', 'PayHere Settings')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>PayHere Settings</h1>
</section>

<!-- Main content -->
<section class="content">
    {!! Form::open(['url' => action([\Modules\PayHere\Http\Controllers\PayHereController::class, 'updateSettings']), 'method' => 'post', 'id' => 'payhere_settings_form' ]) !!}

    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fas fa-rocket"></i> Getting Started with PayHere</h3>
                </div>
                <div class="box-body">
                    <ol>
                        <li><strong>Rename Payment Label:</strong> Go to <a href="{{action([\App\Http\Controllers\BusinessController::class, 'getBusinessSettings'])}}#custom_labels_tab" target="_blank">Settings > Business Settings > Custom Labels</a> and rename <strong>"Custom Payment 1"</strong> to <strong>"PayHere"</strong>.</li>
                        <li><strong>Setup Internal Account:</strong> Go to <a href="{{action([\App\Http\Controllers\AccountController::class, 'index'])}}" target="_blank">Account Management > List Accounts</a> and create a Bank/Cash account (e.g., "PayHere Account").</li>
                        <li><strong>Link Account:</strong> Select your newly created account in the <strong>"Internal Account Mapping"</strong> dropdown below and click <strong>"Update Settings"</strong>.</li>
                    </ol>
                    <p class="text-muted"><i class="fas fa-info-circle"></i> This ensures that payments are correctly labeled in your reports and linked to your financial accounts.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fas fa-cog"></i> PayHere Configuration</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <!-- Merchant Settings -->
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_merchant_id', 'PayHere Merchant ID:') !!}
                                {!! Form::text('payhere_merchant_id', $payhere_setting?->merchant_id ?? '', ['class' => 'form-control', 'placeholder' => 'PayHere Merchant ID']); !!}
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_secret', 'PayHere Secret:') !!}
                                {!! Form::text('payhere_secret', $payhere_setting?->secret ?? '', ['class' => 'form-control', 'placeholder' => 'PayHere Secret']); !!}
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_account_id', 'PayHere Account ID:') !!}
                                {!! Form::text('payhere_account_id', $payhere_setting?->account_id ?? '', ['class' => 'form-control', 'placeholder' => 'PayHere Account ID']); !!}
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        
                        <!-- Mode & Account Settings -->
                        <div class="col-sm-3">
                            <div class="form-group">
                                {!! Form::label('payhere_mode', 'PayHere Mode:') !!}
                                {!! Form::select('payhere_mode', ['sandbox' => 'Sandbox', 'live' => 'Live'], $payhere_setting?->mode ?? 'sandbox', ['class' => 'form-control select2']); !!}
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                {!! Form::label('payhere_account_id_mapping', 'Internal Account Mapping:') !!}
                                {!! Form::select('payhere_pos_account_id', $accounts, $payhere_setting?->pos_account_id ?? null, ['class' => 'form-control select2', 'placeholder' => 'Select Account', 'style' => 'width: 100%;']); !!}
                                <p class="help-block">Payments made via PayHere will be recorded against this internal POS account.</p>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                {!! Form::label('payhere_payment_method', 'Payment Slot Mapping:') !!}
                                {!! Form::select('payhere_payment_method', $payment_methods, $payhere_setting?->payment_method ?? 'custom_pay_1', ['class' => 'form-control select2', 'style' => 'width: 100%;']); !!}
                                <p class="help-block">Select an empty "Custom Payment" slot to use for PayHere.</p>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                {!! Form::label('payhere_payment_label', 'Display Label:') !!}
                                @php
                                    $current_slot = $payhere_setting?->payment_method ?? 'custom_pay_1';
                                    $default_label = $custom_labels['payments'][$current_slot] ?? 'PayHere';
                                @endphp
                                {!! Form::text('payhere_payment_label', $default_label, ['class' => 'form-control', 'placeholder' => 'e.g. PayHere']); !!}
                                <p class="help-block">This will update the label in your POS and Reports.</p>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <div class="col-sm-12">
                            <hr style="margin: 20px 0;">
                        </div>
                        
                        <!-- Convenience Fee Settings -->
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_fee_percentage', 'Fee Percentage (%):') !!}
                                @php
                                    $fee_percentage = $payhere_setting?->fee_percentage ?? 3.00;
                                @endphp
                                {!! Form::number('payhere_fee_percentage', $fee_percentage, ['class' => 'form-control', 'placeholder' => 'e.g. 3.00', 'min' => '0', 'max' => '100', 'step' => '0.01']); !!}
                                <p class="help-block">Percentage of invoice to charge as convenience fee (e.g., 3 for 3%).</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_max_fee_amount', 'Maximum Fee Amount:') !!}
                                @php
                                    $max_fee = $payhere_setting?->max_fee_amount ?? 0;
                                @endphp
                                {!! Form::number('payhere_max_fee_amount', $max_fee, ['class' => 'form-control', 'placeholder' => 'e.g. 500', 'min' => '0', 'step' => '0.01']); !!}
                                <p class="help-block">Maximum convenience fee to charge. Set 0 for no limit.</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                {!! Form::label('payhere_enable_fee', 'Enable Convenience Fee:') !!}
                                <div class="checkbox">
                                    <label>
                                        @php
                                            $fee_enabled = isset($payhere_setting->enable_fee) ? $payhere_setting->enable_fee : true;
                                        @endphp
                                        {!! Form::checkbox('payhere_enable_fee', '1', $fee_enabled); !!}
                                        Charge convenience fee on PayHere payments
                                    </label>
                                </div>
                                <p class="help-block">When enabled, a convenience fee will be added to the invoice.</p>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        
                        <!-- Single Save Button -->
                        <div class="col-sm-12 text-center" style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update Settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 30px;">
        <div class="col-md-12 text-center">
            <img src="https://www.payhere.lk/downloads/images/payhere_long_banner_dark.png" alt="PayHere Official" style="max-width: 450px; width: 100%;">
        </div>
    </div>
    {!! Form::close() !!}
</section>
@stop
