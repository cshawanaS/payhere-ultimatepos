@php
    $token = request()->route('token');
    $transaction = \App\Transaction::where('invoice_token', $token)->with(['business', 'contact'])->first();
    $payhere_setting = \Modules\PayHere\Entities\PayHereSetting::where('business_id', $transaction->business_id)->first();
@endphp

@if(!empty($transaction) && !empty($payhere_setting) && !empty($payhere_setting->merchant_id) && $transaction->payment_status != 'paid')
    @php
        $payhere_merchant_id = $payhere_setting->merchant_id;
        $payhere_secret = $payhere_setting->secret;
        
        $business_util = new \App\Utils\BusinessUtil();
        $business_details = $business_util->getDetails($transaction->business_id);
        
        $payhere_currency = $business_details->currency_code;
        $paid_amount = \App\TransactionPayment::where('transaction_id', $transaction->id)->sum('amount');
        $total_payable = $transaction->final_total - $paid_amount;
        $payhere_amount = number_format($total_payable, 2, '.', '');
        $payhere_order_id = $transaction->invoice_no;
        $payhere_mode = $payhere_setting->mode ?? 'sandbox';
        
        $hash_str = $payhere_merchant_id . $payhere_order_id . $payhere_amount . $payhere_currency . strtoupper(md5($payhere_secret));
        $payhere_hash = strtoupper(md5($hash_str));
    @endphp

    <div class="row">
        <div class="col-md-12 text-center hidden-print" style="margin-top: 20px;">
            <h4 style="margin-bottom: 10px;">Pay with</h4>
            
            <!-- PayHere JS SDK -->
            <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>

            <button type="button" id="payhere_payment_button" style="border: none; background: none; padding: 0; cursor: pointer;">
                <img src="https://www.payhere.lk/downloads/images/payhere_long_banner_dark.png" alt="Pay with PayHere" style="width: 100%; max-width: 400px;">
            </button>

            <div id="payhere_loading_overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; justify-content: center; align-items: center;">
                <h3 style="color: #333;">Processing Payment... Please wait.</h3>
            </div>

            <script>
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
                    window.location.href = "{{ route('payhere.return', ['id' => $transaction->id]) }}?order_id=" + orderId;
                };

                payhere.onDismissed = function onDismissed() {
                    document.getElementById('payhere_loading_overlay').style.display = 'none';
                };

                payhere.onError = function onError(error) {
                    alert("Payment Error: " + error);
                    document.getElementById('payhere_loading_overlay').style.display = 'none';
                };

                document.getElementById('payhere_payment_button').onclick = function (e) {
                    document.getElementById('payhere_loading_overlay').style.display = 'flex';
                    payhere.startPayment(payment);
                };
            </script>
        </div>
    </div>
@endif
