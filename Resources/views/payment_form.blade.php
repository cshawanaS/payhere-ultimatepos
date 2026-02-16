@extends('layouts.guest')
@section('title', $title)
@section('content')

<div class="container">
    <div class="spacer"></div>
    <div class="row">
        <div class="col-xs-12 col-md-6 col-md-offset-3">
            <div class="box box-primary">
                <div class="box-body">
                    <table class="table no-border">
                        <tr>
                            @if(!empty($transaction->business->logo))
                                <td class="width-50 text-center">
                                    <img src="{{ asset( 'uploads/business_logos/' . $transaction->business->logo ) }}" alt="Logo" style="max-width: 80%;">
                                </td>
                            @endif
                            <td class="text-center">
                                <address>
                                <strong>{{ $transaction->business->name }}</strong><br>
                                {{ $transaction->location->name ?? '' }}
                                @if(!empty($transaction->location->landmark))
                                    <br>{{$transaction->location->landmark}}
                                @endif
                                @if(!empty($transaction->location->city) || !empty($transaction->location->state) || !empty($transaction->location->country))
                                    <br>{{implode(',', array_filter([$transaction->location->city, $transaction->location->state, $transaction->location->country]))}}
                                @endif
                              
                                @if(!empty($transaction->business->tax_number_1))
                                    <br>{{$transaction->business->tax_label_1}}: {{$transaction->business->tax_number_1}}
                                @endif

                                @if(!empty($transaction->business->tax_number_2))
                                    <br>{{$transaction->business->tax_label_2}}: {{$transaction->business->tax_number_2}}
                                @endif

                                @if(!empty($transaction->location->mobile))
                                    <br>@lang('contact.mobile'): {{$transaction->location->mobile}}
                                @endif
                                @if(!empty($transaction->location->email))
                                    <br>@lang('business.email'): {{$transaction->location->email}}
                                @endif
                            </address>
                            </td>
                        </tr>
                    </table>
                    <h4 class="box-title">@lang('lang_v1.payment_for_invoice_no'): {{$transaction->invoice_no}}</h4>
                    <table class="table no-border">
                        <tr>
                            <td>
                                <strong>@lang('contact.customer'):</strong><br>
                                {!!$transaction->contact->contact_address!!}
                            </td>
                        </tr>
                        <tr>
                            <td><strong>@lang('sale.sale_date'):</strong> {{$date_formatted}}</td>
                        </tr>
                        <tr>
                            <td>
                                <h4>@lang('sale.total_amount'): <span>{{$total_amount}}</span></h4>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4>@lang('sale.total_paid'): <span>{{$total_paid}}</span></h4>
                            </td>
                        </tr>
                    </table>

                    @if($transaction->payment_status != 'paid')
                    <table class="table no-border">
                        <tr>
                            <td><h4>@lang('sale.total_payable'): <span>{{$total_payable_formatted}}</span></h4></td>
                        </tr>
                    </table>
                    <div class="spacer"></div>
                    <div class="spacer"></div>
                    <div class="width-50 text-center f-left">
                        <form action="{{route('confirm_payment', ['id' => $transaction->id])}}" method="POST">
                            <input type="hidden" name="gateway" value="razorpay">
                                <!-- Note that the amount is in paise -->
                            <script
                                src="https://checkout.razorpay.com/v1/checkout.js"
                                data-key="{{$pos_settings['razor_pay_key_id']}}"
                                data-amount="{{$total_payable*100}}"
                                data-buttontext="Pay with Razorpay"
                                data-name="{{$transaction->business->name}}"
                                data-theme.color="#3c8dbc"
                            ></script>
                            {{ csrf_field() }}
                        </form>
                    </div>
                        @if(!empty($pos_settings['stripe_public_key']) && !empty($pos_settings['stripe_secret_key']))
                            @php
                                $code = strtolower($business_details->currency_code);
                            @endphp

                            <div class="width-50 text-center f-left">
                                <form action="{{route('confirm_payment', ['id' => $transaction->id])}}" method="POST">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="gateway" value="stripe">
                                    <script
                                            src="https://checkout.stripe.com/checkout.js" class="stripe-button"
                                            data-key="{{$pos_settings['stripe_public_key']}}"
                                            data-amount="@if(in_array($code, ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'])) {{$total_payable}} @else {{$total_payable*100}} @endif"
                                            data-name="{{$transaction->business->name}}"
                                            data-description="Pay with stripe"
                                            data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
                                            data-locale="auto"
                                            data-currency="{{$code}}">
                                    </script>
                                </form>
                            </div>
                        @endif

                        @if(!empty($pos_settings['payhere_merchant_id']) && !empty($pos_settings['payhere_secret']))
                             @php
                                $payhere_merchant_id = $pos_settings['payhere_merchant_id'];
                                $payhere_secret = $pos_settings['payhere_secret'];
                                $payhere_currency = $business_details->currency_code;
                                $payhere_amount = number_format($total_payable, 2, '.', '');
                                $payhere_order_id = $transaction->invoice_no;
                                $payhere_mode = $pos_settings['payhere_mode'] ?? 'sandbox';
                                $action_url = ($payhere_mode == 'live') ? 'https://www.payhere.lk/pay/checkout' : 'https://sandbox.payhere.lk/pay/checkout';
                                
                                $hash_str = $payhere_merchant_id . $payhere_order_id . $payhere_amount . $payhere_currency . strtoupper(md5($payhere_secret));
                                $payhere_hash = strtoupper(md5($hash_str));
                            @endphp
                            
                            <div class="width-50 text-center f-left" style="margin-top: 10px;">
                                <h4 style="margin-bottom: 10px;">Pay with</h4>
                                <!-- PayHere JS SDK -->
                                <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>

                                <button type="button" id="payhere_payment_button" style="border: none; background: none; padding: 0;">
                                    <img src="https://www.payhere.lk/downloads/images/payhere_long_banner_dark.png" alt="Pay with PayHere" style="width: 300px;">
                                </button>
                                
                                <div id="payhere_loading_overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; justify-content: center; align-items: center;">
                                    <h3 style="color: #333;">Processing Payment... Please wait.</h3>
                                </div>

                                <script>
                                    // Immediate check to disable button and show overlay
                                    if(new URLSearchParams(window.location.search).get('auto_payhere') === 'true'){
                                        var btn = document.getElementById('payhere_payment_button');
                                        if(btn) {
                                            btn.style.pointerEvents = 'none';
                                            btn.style.opacity = '0.5';
                                        }
                                        document.getElementById('payhere_loading_overlay').style.display = 'flex';
                                    }

                                    // Payment Data Object
                                    var payment = {
                                        "sandbox": "{{ $payhere_mode == 'live' ? false : true }}",
                                        "merchant_id": "{{ $payhere_merchant_id }}",
                                        "return_url": "{{ route('payhere.return', ['id' => $transaction->id]) }}",
                                        "cancel_url": "{{ route('payhere.return', ['id' => $transaction->id]) }}",
                                        "notify_url": "{{ route('payhere.notify') }}",
                                        "order_id": "{{ $payhere_order_id }}",
                                        "items": "Invoice {{ $payhere_order_id }}",
                                        "amount": "{{ $payhere_amount }}",
                                        "currency": "{{ $payhere_currency }}",
                                        "hash": "{{ $payhere_hash }}",
                                        "first_name": "{{ $transaction->contact->first_name }}",
                                        "last_name": "{{ $transaction->contact->last_name ?? '' }}",
                                        "email": "{{ $transaction->contact->email ?? '' }}",
                                        "phone": "{{ $transaction->contact->mobile }}",
                                        "address": "{{ $transaction->contact->address_line_1 }}",
                                        "city": "{{ $transaction->contact->city }}",
                                        "country": "{{ $transaction->contact->country }}",
                                        "custom_1": "{{ $transaction->id }}",
                                        "custom_2": "{{ $transaction->business_id }}"
                                    };

                                    // Event Handlers
                                    payhere.onCompleted = function onCompleted(orderId) {
                                        console.log("Payment completed. OrderID:" + orderId);
                                        // Redirect to return_url to finalize
                                        window.location.href = "{{ route('payhere.return', ['id' => $transaction->id]) }}?order_id=" + orderId;
                                    };

                                    payhere.onDismissed = function onDismissed() {
                                        console.log("Payment dismissed");
                                        // Re-enable button and hide overlay
                                        var btn = document.getElementById('payhere_payment_button');
                                        btn.style.pointerEvents = 'auto';
                                        btn.style.opacity = '1';
                                        
                                        var overlay = document.getElementById('payhere_loading_overlay');
                                        overlay.style.display = 'none';
                                    };

                                    payhere.onError = function onError(error) {
                                        console.log("Error:" + error);
                                        alert("Payment Error: " + error);
                                        // Re-enable button and hide overlay
                                        var btn = document.getElementById('payhere_payment_button');
                                        btn.style.pointerEvents = 'auto';
                                        btn.style.opacity = '1';

                                        var overlay = document.getElementById('payhere_loading_overlay');
                                        overlay.style.display = 'none';
                                    };

                                    // Trigger Button
                                    document.getElementById('payhere_payment_button').onclick = function (e) {
                                        payhere.startPayment(payment);
                                    };
                                </script>
                            </div>
                        @endif
                    @else
                        <table class="table no-border">
                            <tr>
                                <td><h4>@lang('sale.payment_status'): <span class="text-success">@lang('lang_v1.paid')</span></h4></td>
                            </tr>
                        </table>
                    @endif
                    <div class="spacer"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function(){
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('auto_payhere') === 'true'){
                 // Trigger PayHere popup automatically
                 if(typeof payhere !== 'undefined'){
                     // Small delay to ensure SDK loads
                     setTimeout(function(){
                         payhere.startPayment(payment);
                     }, 1000);
                 }
            }
        });
    </script>
@endsection