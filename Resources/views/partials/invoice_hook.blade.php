@php
    $token = request()->route('token');
    $transaction = \App\Transaction::where('invoice_token', $token)->with(['business', 'contact'])->first();
    $payhere_setting = \Modules\PayHere\Entities\PayHereSetting::where('business_id', $transaction->business_id)->first();
    
    if (!empty($transaction) && !empty($payhere_setting) && !empty($payhere_setting->merchant_id)) {
        $payhere_merchant_id = $payhere_setting->merchant_id;
        $payhere_secret = $payhere_setting->secret;
        
        $business_util = new \App\Utils\BusinessUtil();
        $business_details = $business_util->getDetails($transaction->business_id);
        
        $payhere_currency = $business_details->currency_code;
        
        // Calculate remaining balance
        $paid_amount = \App\TransactionPayment::where('transaction_id', $transaction->id)->sum('amount');
        $remaining_balance = $transaction->final_total - $paid_amount;
        
        $feeService = new \Modules\PayHere\Services\PayHereFeeService();
        $feeData = $feeService->calculateConvenienceFee($remaining_balance, $payhere_setting);
        
        $total_payable = $feeData['total_payable'];
        $convenience_fee = $feeData['convenience_fee'];
        $total_with_fee = $feeData['total_with_fee'];
        $payhere_amount = $feeData['payhere_amount'];
        $payhere_order_id = $transaction->invoice_no;
        $payhere_mode = $payhere_setting->mode ?? 'sandbox';
        
        $hash_str = $payhere_merchant_id . $payhere_order_id . $payhere_amount . $payhere_currency . strtoupper(md5($payhere_secret));
        $payhere_hash = strtoupper(md5($hash_str));
        
        $items_label = "Invoice " . $payhere_order_id;
    }
@endphp

@if(!empty($transaction) && !empty($payhere_setting) && !empty($payhere_setting->merchant_id) && $transaction->payment_status != 'paid')
    <!-- PayHere JS SDK -->
    <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>

    <div id="payhere-payment-widget" class="payment-widget-item hidden-print" style="display: none;">
        <div class="payhere-banner-inner">
            
            <div style="margin-bottom: 15px;">
                <a href="https://www.payhere.lk" target="_blank" style="text-decoration: none; display: inline-block;">
                    <img src="https://www.payhere.lk/downloads/images/payhere_square_banner_dark.png" alt="PayHere" style="width: 150px; border-radius: 8px;">
                </a>
            </div>

            <div style="font-size: 13px; color: #555; margin-bottom: 8px; line-height: 1.4;">
                Pay Securely <br>
                <span style="color: #333; font-weight: 700; font-size: 15px;">{{ $payhere_currency }} {{ number_format($total_with_fee, 2) }}</span>
                @if($convenience_fee > 0)
                    <br><small style="font-size: 10px; color: #777;">(incl. processing fees)</small>
                @endif
            </div>

            <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                <button type="button" id="payhere_top_payment_button" style="background: #0044bb; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 2px 4px rgba(0,68,187,0.2);">
                    <span style="font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">PAY NOW</span>
                </button>
            </div>
            
            <div style="margin-top: 12px; font-size: 10px; color: #888; text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="fa fa-lock" style="color: #28a745;"></i>
                <span>SSL SECURE PAYMENT</span>
            </div>
        </div>
    </div>

    <div id="payhere_loading_overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 10000; justify-content: center; align-items: center;">
        <div class="text-center">
            <i class="fa fa-refresh fa-spin fa-3x fa-fw" style="color: #0044bb;"></i>
            <h3 style="color: #333; margin-top: 15px; font-weight: 600;">Connecting to PayHere...</h3>
        </div>
    </div>

    <style>
        .payment-widgets-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px auto 30px;
            width: 100%;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .payment-widget-item {
            flex: 1 1 320px;
            max-width: 100%;
        }
        .payhere-banner-inner {
            padding: 20px;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            background-color: #ffffff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border-top: 4px solid #0044bb;
            text-align: center;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        .payhere-banner-inner:hover {
            transform: translateY(-2px);
        }
        #payhere_top_payment_button:hover { 
            background: #0033aa !important;
            box-shadow: 0 4px 8px rgba(0,68,187,0.3);
        }
        @media (max-width: 600px) {
            .payment-widget-item {
                flex: 1 1 100%;
            }
        }
    </style>

    <script>
        (function() {
            var containerId = 'payment-widgets-top-container';
            var container = document.getElementById(containerId);
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'payment-widgets-container hidden-print';
                var currentScript = document.currentScript || (function() {
                    var scripts = document.getElementsByTagName('script');
                    return scripts[scripts.length - 1];
                })();
                currentScript.parentNode.insertBefore(container, currentScript);
            }
            var widget = document.getElementById('payhere-payment-widget');
            if (widget && container) {
                container.appendChild(widget);
                widget.style.display = 'block';
            }

            var payment = {
                "sandbox": {{ $payhere_mode == 'live' ? 'false' : 'true' }},
                "merchant_id": "{{ $payhere_merchant_id }}",
                "return_url": "{{ route('payhere.return', ['id' => $transaction->id]) }}",
                "cancel_url": "{{ route('payhere.return', ['id' => $transaction->id]) }}",
                "notify_url": "{{ route('payhere.notify') }}",
                "order_id": "{{ $payhere_order_id }}",
                "items": "{{ $items_label }}",
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

            payhere.onCompleted = function onCompleted(orderId) {
                window.location.href = "{{ route('payhere.return', ['id' => $transaction->id]) }}?order_id=" + orderId;
            };

            payhere.onDismissed = function onDismissed() {
                document.getElementById('payhere_loading_overlay').style.display = 'none';
            };

            payhere.onError = function onError(error) {
                alert("Payment Error: " + error);
                document.getElementById('payhere_loading_overlay').style.display = 'none';
            };

            var btn = document.getElementById('payhere_top_payment_button');
            if (btn) {
                btn.onclick = function (e) {
                    document.getElementById('payhere_loading_overlay').style.display = 'flex';
                    payhere.startPayment(payment);
                };
            }
        })();
    </script>
@endif
