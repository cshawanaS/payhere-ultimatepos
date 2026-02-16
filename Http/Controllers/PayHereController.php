<?php

namespace Modules\PayHere\Http\Controllers;

use Illuminate\Http\Request;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use App\Business;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Events\TransactionPaymentAdded;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use App\Utils\ModuleUtil;
use App\Utils\BusinessUtil;
use Modules\PayHere\Entities\PayHereSetting;

class PayHereController extends Controller
{
    protected $transactionUtil;
    protected $businessUtil;
    protected $moduleUtil;

    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, BusinessUtil $businessUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessUtil = $businessUtil;
    }

    /**
     * Display PayHere Settings
     */
    public function index()
    {
        if (!auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business = Business::where('id', $business_id)->first();
        
        $payhere_setting = PayHereSetting::where('business_id', $business_id)->first();
        
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = \App\Account::forDropdown($business_id, false, false);
        }

        // Get available custom payment types to show their current labels
        $custom_labels = $business->custom_labels;
        if (!is_array($custom_labels)) {
            $custom_labels = json_decode($custom_labels, true) ?? [];
        }

        $payment_methods = [];
        for ($i=1; $i<=7; $i++) {
            $key = 'custom_pay_' . $i;
            $current_label = $custom_labels['payments'][$key] ?? 'Custom Payment ' . $i;
            $payment_methods[$key] = 'Slot ' . $i . ' (Current Label: ' . $current_label . ')';
        }

        return view('payhere::index')
            ->with(compact('business', 'payhere_setting', 'accounts', 'payment_methods', 'custom_labels'));
    }

    /**
     * Update PayHere Settings
     */
    public function updateSettings(Request $request)
    {
        if (!auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            
            PayHereSetting::updateOrCreate(
                ['business_id' => $business_id],
                [
                    'merchant_id' => $request->input('payhere_merchant_id'),
                    'secret' => $request->input('payhere_secret'),
                    'account_id' => $request->input('payhere_account_id'),
                    'pos_account_id' => $request->input('payhere_pos_account_id'),
                    'mode' => $request->input('payhere_mode'),
                    'payment_method' => $request->input('payhere_payment_method')
                ]
            );

            // Auto-update Global Custom Label
            $payment_method = $request->input('payhere_payment_method');
            $payment_label = $request->input('payhere_payment_label');

            if (!empty($payment_method) && !empty($payment_label)) {
                $business = Business::find($business_id);
                $custom_labels = $business->custom_labels;
                if (!is_array($custom_labels)) {
                    $custom_labels = json_decode($custom_labels, true) ?? [];
                }
                $custom_labels['payments'][$payment_method] = $payment_label;
                $business->custom_labels = $custom_labels;
                $business->save();
            }

            $output = ['success' => 1,
                'msg' => __('business.settings_updated_success'),
            ];
        } catch (\Exception $e) {
            Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * PayHere Webhook Notify URL
     */
    public function notify(Request $request)
    {
        $validation = $this->validateRequest($request, true);
        if ($validation['status'] !== 'success') {
            return response()->json(['status' => $validation['status']], 400);
        }

        $transaction = $validation['transaction'];
        $amount = $request->input('payhere_amount');
        $currency = $request->input('payhere_currency');
        $payment_id = $request->input('payment_id');

        // Idempotency Check
        if ($this->isPaymentExists($payment_id)) {
            Log::info("PayHere Module: Duplicate Callback Ignored", ['payment_id' => $payment_id]);
            return response()->json(['status' => 'duplicate_ignored']);
        }

        if ($request->input('status_code') == 2) {
            $this->processPayment($transaction, $amount, $currency, $payment_id, $validation['payhere_setting']);
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'ignored']);
    }

    /**
     * PayHere Return URL
     */
    public function paymentReturn(Request $request, $id = null)
    {
        // Debug: Log incoming return request to troubleshoot validation failures
        Log::debug("PayHere Module: Return URL Hit", $request->all());

        // 1. Recover Transaction info from Route if missing in Request
        if ($id && !$request->has('custom_1')) {
            $request->merge(['custom_1' => $id]);
        }

        // 2. Check if payment was already successfully processed (by Notify Webhook)
        $transaction_id = $request->input('custom_1');
        if (!empty($transaction_id)) {
            $transaction = Transaction::find($transaction_id);
            if ($transaction) {
                if(!$request->has('custom_2')){
                    $request->merge(['custom_2' => $transaction->business_id]);
                }

                $payhere_setting = PayHereSetting::where('business_id', $transaction->business_id)->first();
                $payment_method = $payhere_setting->payment_method ?? 'custom_pay_1';

                $payment_exists = TransactionPayment::where('transaction_id', $transaction->id)
                                                  ->where('method', $payment_method)
                                                  ->exists();
                
                if ($payment_exists) {
                     $link = $this->transactionUtil->getInvoiceUrl($transaction->id, $transaction->business_id);
                     return redirect($link)->with('status', ['success' => 1, 'msg' => __('purchase.payment_added_success')]);
                }
            }
        }

        $validation = $this->validateRequest($request, false);
        
        if ($validation['status'] !== 'success' || $request->input('status_code') != 2) {
            $msg = __('messages.something_went_wrong');
            if ($validation['status'] === 'mismatch' || $validation['status'] === 'failed_auth') {
                $msg = "Payment Verification Failed";
            }
            if ($request->input('status_code') != 2) {
                 $msg = "Payment Failed or Canceled";
            }
            
            if (!empty($validation['transaction'])) {
                $transaction = $validation['transaction'];
            } elseif (!empty($transaction_id)) {
                $transaction = Transaction::find($transaction_id);
            }

            if (!empty($transaction)) {
                $link = $this->transactionUtil->getInvoiceUrl($transaction->id, $transaction->business_id);
                return redirect($link)->with('status', ['success' => 0, 'msg' => $msg]);
            }
            
            return redirect('/')->with('status', ['success' => 0, 'msg' => $msg]);
        }

        $transaction = $validation['transaction'];
        $amount = $request->input('payhere_amount');
        $currency = $request->input('payhere_currency');
        $payment_id = $request->input('payment_id');

        if (!$this->isPaymentExists($payment_id)) {
             $this->processPayment($transaction, $amount, $currency, $payment_id, $validation['payhere_setting']);
        }

        $link = $this->transactionUtil->getInvoiceUrl($transaction->id, $transaction->business_id);
        return redirect($link)->with('status', ['success' => 1, 'msg' => __('purchase.payment_added_success')]);
    }

    private function validateRequest(Request $request, $log_failures = true)
    {
        $validator = Validator::make($request->all(), [
            'merchant_id'      => 'required',
            'order_id'         => 'required',
            'payhere_amount'   => 'required|numeric',
            'payhere_currency' => 'required',
            'status_code'      => 'required|integer',
            'md5sig'           => 'required',
            'payment_id'       => 'required',
            'custom_1'         => 'required', // Transaction ID
            'custom_2'         => 'required', // Business ID
        ]);

        if ($validator->fails()) {
            if ($log_failures) {
                Log::warning("PayHere Module: Validation Failed", $validator->errors()->toArray());
            }
            return ['status' => 'validation_error'];
        }

        $business_id = $request->input('custom_2');
        $transaction_id = $request->input('custom_1');
        $order_id = $request->input('order_id');
        
        $business = Business::find($business_id);
        if (!$business) {
            return ['status' => 'business_not_found'];
        }
        
        $payhere_setting = PayHereSetting::where('business_id', $business_id)->first();
        if (!$payhere_setting) {
            return ['status' => 'settings_not_found'];
        }

        $merchant_id = $payhere_setting->merchant_id;
        $merchant_secret = $payhere_setting->secret;

        if ($request->input('merchant_id') != $merchant_id) {
             Log::alert("PayHere Module: Merchant ID Mismatch");
             return ['status' => 'invalid_merchant'];
        }

        $local_md5sig = strtoupper(md5(
            $request->input('merchant_id') . 
            $request->input('order_id') . 
            $request->input('payhere_amount') . 
            $request->input('payhere_currency') . 
            $request->input('status_code') . 
            strtoupper(md5($merchant_secret))
        ));

        if (!hash_equals($local_md5sig, $request->input('md5sig'))) {
             Log::critical("PayHere Module: Signature Mismatch");
             return ['status' => 'failed_auth'];
        }

        $transaction = Transaction::where('id', $transaction_id)
                                  ->where('business_id', $business_id)
                                  ->first();

        if (!$transaction || $transaction->invoice_no !== $order_id) {
             return ['status' => 'mismatch'];
        }

        return ['status' => 'success', 'transaction' => $transaction, 'business' => $business, 'payhere_setting' => $payhere_setting];
    }

    private function isPaymentExists($payment_id) {
        return TransactionPayment::where('transaction_no', $payment_id)
                                 ->where('method', 'custom_pay_1')
                                 ->exists();
    }

    private function processPayment($transaction, $amount, $currency, $payment_id, $payhere_setting)
    {
        // Fix: Mock session 'user.id' for Accounting Module compatibility
        // The Accounting module listener calls request()->session()->get('user.id')
        $business = Business::find($transaction->business_id);
        if ($business && $business->owner_id) {
            session(['user.id' => $business->owner_id]);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            $total_paid_already = $this->transactionUtil->getTotalPaid($transaction->id);
            $current_inv_balance = $transaction->final_total - $total_paid_already;

            $target_account_id = $payhere_setting->pos_account_id ?? null;
            $payment_method = $payhere_setting->payment_method ?? 'custom_pay_1';

            // Use the now authenticated user ID (from session mock) or fallback to 1
            $created_by = session('user.id') ?? (auth()->id() ?? 1);

            $payment_data = [
                'transaction_id' => $transaction->id,
                'business_id'    => $transaction->business_id,
                'amount'         => $amount,
                'method'         => $payment_method,
                'transaction_no' => $payment_id,
                'account_id'     => $target_account_id,
                'paid_on'        => Carbon::now()->toDateTimeString(),
                'created_by'     => $created_by,
                'payment_for'    => $transaction->contact_id,
                'note'           => 'Online Payment Ref: ' . $payment_id . ' (' . $currency . ')',
                'payment_ref_no' => $this->transactionUtil->generateReferenceNumber('sell_payment', $this->transactionUtil->setAndGetReferenceCount('sell_payment', $transaction->business_id), $transaction->business_id)
            ];

            $payment = TransactionPayment::create($payment_data);

            if ($amount > ($current_inv_balance + 0.01)) {
                $excess = $this->transactionUtil->payAtOnce($payment, 'sell');
                if ($excess > 0) {
                    $this->transactionUtil->updateContactBalance($transaction->contact_id, $excess, 'add');
                }
            }

            if(!empty($target_account_id)){
                $account_transaction_data = $payment_data;
                $account_transaction_data['transaction_type'] = $transaction->type;
                event(new TransactionPaymentAdded($payment, $account_transaction_data));
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            
            DB::commit();
            Log::info("PayHere Module: Payment Processed Success", ['order_id' => $transaction->invoice_no]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PayHere Module Processing Error: " . $e->getMessage());
            throw $e;
        }
    }
}
