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
use Modules\PayHere\Services\PayHereFeeService;

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
            
            // Server-side validation for fee settings
            $validatedData = $request->validate([
                'payhere_fee_percentage' => 'nullable|numeric|min:0|max:100',
                'payhere_max_fee_amount' => 'nullable|numeric|min:0',
                'payhere_enable_fee' => 'nullable|boolean',
            ]);

            // Coerce and cast values to float
            $fee_percentage = $validatedData['payhere_fee_percentage'] ?? 3.00;
            $max_fee_amount = $validatedData['payhere_max_fee_amount'] ?? 0;
            $enable_fee = $request->has('payhere_enable_fee') ? true : false;

            PayHereSetting::updateOrCreate(
                ['business_id' => $business_id],
                [
                    'merchant_id' => $request->input('payhere_merchant_id'),
                    'secret' => $request->input('payhere_secret'),
                    'account_id' => $request->input('payhere_account_id'),
                    'pos_account_id' => $request->input('payhere_pos_account_id'),
                    'mode' => $request->input('payhere_mode'),
                    'payment_method' => $request->input('payhere_payment_method'),
                    'fee_percentage' => (float) $fee_percentage,
                    'max_fee_amount' => (float) $max_fee_amount,
                    'enable_fee' => $enable_fee
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
        
        // DEBUG: Log webhook input
        Log::debug("PayHere Webhook: All Input Keys: " . implode(', ', array_keys($request->all())));
        Log::debug("PayHere Webhook: payment_id: " . ($payment_id ?? 'NULL'));

        // Idempotency Check
        $payment_method = $validation['payhere_setting']->payment_method ?? 'custom_pay_1';
        
        // Check if payment already exists (original logic: check both transaction_no and method)
        if ($this->isPaymentExists($payment_id, $payment_method)) {
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
        $payment_id = $request->input('payment_id'); // Move this definition earlier
        
        if (!empty($transaction_id)) {
            $transaction = Transaction::find($transaction_id);
            if ($transaction) {
                if(!$request->has('custom_2')){
                    $request->merge(['custom_2' => $transaction->business_id]);
                }

                $payhere_setting = PayHereSetting::where('business_id', $transaction->business_id)->first();
                $payment_method = $payhere_setting->payment_method ?? 'custom_pay_1';
                
                // Check if payment already exists for this transaction (original logic)
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
        
        // DEBUG: Log all input to diagnose payment_id issue
        Log::debug("PayHere Return URL: All Input Keys: " . implode(', ', array_keys($request->all())));
        Log::debug("PayHere Return URL: payment_id from input: " . ($request->input('payment_id') ?? 'NULL'));
        Log::debug("PayHere Return URL: order_id from input: " . ($request->input('order_id') ?? 'NULL'));
        
        $payment_id = $request->input('payment_id');

        $payment_method = $validation['payhere_setting']->payment_method ?? 'custom_pay_1';
        
        // Check if payment already exists (original logic: check both transaction_no and method)
        if (!$this->isPaymentExists($payment_id, $payment_method)) {
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

    private function isPaymentExists($payment_id, $payment_method = 'custom_pay_1') {
        // Original logic: check both transaction_no AND method
        return TransactionPayment::where('transaction_no', $payment_id)
                                 ->where('method', $payment_method)
                                 ->exists();
    }

    /**
     * Process PayHere Payment - Option B: Add Fee FIRST, then record full payment
     * This ensures invoice balance matches payment amount, preventing customer credit
     */
    private function processPayment($transaction, $amount, $currency, $payment_id, $payHereSetting)
    {
        // DEBUG: Log incoming parameters
        Log::debug("PayHere processPayment: transaction_id=" . $transaction->id . ", amount=" . $amount . ", payment_id=" . ($payment_id ?? 'NULL') . ", currency=" . $currency);
        
        // SECURITY: Final validation before creating payment
        if (empty($payment_id) || !is_string($payment_id) || strlen($payment_id) <= 3) {
            Log::error("PayHere processPayment: Rejecting payment with invalid payment_id", [
                'payment_id' => $payment_id ?? 'NULL',
                'transaction_id' => $transaction->id
            ]);
            throw new \Exception("Invalid payment_id: payment creation rejected");
        }
        
        if (empty($amount) || $amount <= 0) {
            Log::error("PayHere processPayment: Rejecting payment with invalid amount", [
                'amount' => $amount,
                'transaction_id' => $transaction->id
            ]);
            throw new \Exception("Invalid amount: payment creation rejected");
        }
        
        // Fix: Save original session state to prevent leakage
        $original_user_id = session('user.id');
        $business = Business::find($transaction->business_id);
        if ($business && $business->owner_id) {
            session(['user.id' => $business->owner_id]);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            $total_paid_already = $this->transactionUtil->getTotalPaid($transaction->id);
            $current_inv_balance = $transaction->final_total - $total_paid_already;

            // Use explicit server-validated fee from PayHere custom field (custom_2)
            // This replaces the fragile inference of $amount > $current_inv_balance
            $convenience_fee = 0;
            
            // SECURITY: Validate convenience fee against configured caps with epsilon tolerance
            $feeService = new PayHereFeeService();
            $max_fee = $payHereSetting->max_fee_amount ?? 0;
            $epsilon = 0.01;
            
            // Check if a convenience fee was explicitly sent from PayHere
            // This would need to be implemented in the PayHere form to send the fee in custom_2
            // For now, we'll use the existing logic but with proper validation
            if ($amount > $current_inv_balance) {
                $convenience_fee = round($amount - $current_inv_balance, 2);
                
                // Validate against configured caps with epsilon tolerance
                if (!$feeService->validateFeeAmount($convenience_fee, $max_fee, $epsilon)) {
                    // Fee exceeds configured maximum, reject or adjust
                    $convenience_fee = $max_fee;
                }
                
                // Add convenience fee to invoice FIRST (like manual invoice with additional expenses)
                // Only if not already added
                if (empty($transaction->additional_expense_key_1) || $transaction->additional_expense_key_1 !== 'PayHere Convenience Fee') {
                    $transaction->additional_expense_key_1 = 'PayHere Convenience Fee';
                    $transaction->additional_expense_value_1 = $convenience_fee;
                    
                    // CRITICAL: Manually add fee to final_total
                    // UltimatePOS doesn't auto-calculate final_total when saving additional_expense directly
                    $transaction->final_total = $transaction->final_total + $convenience_fee;
                    $transaction->save();
                    
                    Log::info("PayHere Module: Convenience fee added to invoice", [
                        'transaction_id' => $transaction->id,
                        'fee' => $convenience_fee,
                        'old_total' => $transaction->final_total - $convenience_fee,
                        'new_total' => $transaction->final_total
                    ]);
                    
                    // Refresh to get updated final_total
                    $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();
                }
                
                // RECALCULATE balance AFTER adding fee - this is the key!
                // Now the invoice total includes the fee, so balance should match payment
                $total_paid_already = $this->transactionUtil->getTotalPaid($transaction->id);
                $current_inv_balance = $transaction->final_total - $total_paid_already;
            }

            $target_account_id = $payHereSetting->pos_account_id ?? null;
            $payment_method = $payHereSetting->payment_method ?? 'custom_pay_1';

            // Use the now authenticated user ID (from session mock) or fallback to 1
            $created_by = session('user.id') ?? (auth()->id() ?? 1);

            // Build payment note with fee information
            $note = 'Online Payment Ref: ' . $payment_id . ' (' . $currency . ')';
            if ($convenience_fee > 0) {
                $note .= ' - Incl. Convenience Fee: ' . $currency . ' ' . number_format($convenience_fee, 2);
            }

            // Record FULL payment amount (including fee) - like normal invoice payment
            $payment_amount = $amount;

            $payment_data = [
                'transaction_id' => $transaction->id,
                'business_id'    => $transaction->business_id,
                'amount'         => $payment_amount,
                'method'         => $payment_method,
                'transaction_no' => $payment_id,
                'account_id'     => $target_account_id,
                'paid_on'        => Carbon::now()->toDateTimeString(),
                'created_by'     => $created_by,
                'payment_for'    => $transaction->contact_id,
                'note'           => $note,
                'payment_ref_no' => $this->transactionUtil->generateReferenceNumber('sell_payment', $this->transactionUtil->setAndGetReferenceCount('sell_payment', $transaction->business_id), $transaction->business_id)
            ];

            $payment = TransactionPayment::create($payment_data);

            // Only handle overpayment logic when convenience fee is enabled
            // When fee is disabled, payment amount should match invoice balance exactly
            // No need for payAtOnce() which creates duplicate entries
            
            if(!empty($target_account_id)){
                $account_transaction_data = $payment_data;
                $account_transaction_data['transaction_type'] = $transaction->type;
                event(new TransactionPaymentAdded($payment, $account_transaction_data));
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            
            DB::commit();
            Log::info("PayHere Module: Payment Processed Success", [
                'order_id' => $transaction->invoice_no,
                'payment_amount' => $payment_amount,
                'invoice_total' => $transaction->final_total,
                'convenience_fee' => $convenience_fee,
                'balance_after_payment' => $transaction->final_total - $total_paid_already - $payment_amount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("PayHere Module Processing Error: " . $e->getMessage());
            throw $e;
        } finally {
            // Restore original session state to prevent leakage
            if ($original_user_id !== null) {
                session(['user.id' => $original_user_id]);
            } else {
                session()->forget('user.id');
            }
        }
    }
}
