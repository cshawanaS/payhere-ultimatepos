<?php

namespace Modules\PayHere\Services;

use Modules\PayHere\Entities\PayHereSetting;

class PayHereFeeService
{
    /**
     * Calculate convenience fee and related values
     *
     * @param float $total_payable
     * @param PayHereSetting $payhere_setting
     * @return array
     */
    public function calculateConvenienceFee($total_payable, $payhere_setting)
    {
        // Guard against non-positive amounts
        $total_payable = max(0, $total_payable);
        
        $convenience_fee = 0;
        $total_with_fee = $total_payable;
        $fee_display_percent = 0;
        $payhere_amount = 0;

        // Get fee settings with defaults
        $fee_enabled = $payhere_setting->enable_fee ?? false;
        $fee_percentage = $payhere_setting->fee_percentage ?? 3.00;
        $max_fee = $payhere_setting->max_fee_amount ?? 0;

        // Calculate convenience fee based on settings
        if ($fee_enabled && $total_payable > 0) {
            $fee_display_percent = $fee_percentage;
            $convenience_fee_rate = $fee_percentage / 100;
            $convenience_fee = round($total_payable * $convenience_fee_rate, 2);
            
            // Apply max fee limit
            if ($max_fee > 0 && $convenience_fee > $max_fee) {
                $convenience_fee = $max_fee;
            }
            
            $total_with_fee = $total_payable + $convenience_fee;
        }

        // Use total WITH fee for PayHere
        $payhere_amount = number_format($total_with_fee, 2, '.', '');

        return [
            'convenience_fee' => $convenience_fee,
            'total_with_fee' => $total_with_fee,
            'fee_display_percent' => $fee_display_percent,
            'payhere_amount' => $payhere_amount,
            'total_payable' => $total_payable
        ];
    }

    /**
     * Validate and sanitize fee settings
     *
     * @param float|null $fee_percentage
     * @param float|null $max_fee_amount
     * @param bool|null $enable_fee
     * @return array
     */
    public function validateFeeSettings($fee_percentage, $max_fee_amount, $enable_fee)
    {
        // Default values
        $fee_percentage = $fee_percentage ?? 3.00;
        $max_fee_amount = $max_fee_amount ?? 0;
        $enable_fee = $enable_fee ?? false;

        // Validate and sanitize
        $fee_percentage = (float) $fee_percentage;
        $max_fee_amount = (float) $max_fee_amount;
        $enable_fee = (bool) $enable_fee;

        // Ensure fee percentage is between 0 and 100
        if ($fee_percentage < 0) {
            $fee_percentage = 0;
        } elseif ($fee_percentage > 100) {
            $fee_percentage = 100;
        }

        // Ensure max fee amount is non-negative
        if ($max_fee_amount < 0) {
            $max_fee_amount = 0;
        }

        return [
            'fee_percentage' => $fee_percentage,
            'max_fee_amount' => $max_fee_amount,
            'enable_fee' => $enable_fee
        ];
    }

    /**
     * Validate convenience fee against configured caps with epsilon tolerance
     *
     * @param float $fee_amount
     * @param float $max_fee_amount
     * @param float $epsilon
     * @return bool
     */
    public function validateFeeAmount($fee_amount, $max_fee_amount, $epsilon = 0.01)
    {
        if ($max_fee_amount <= 0) {
            return true; // No limit set
        }

        // Use epsilon comparison for float values
        return ($fee_amount - $epsilon) <= $max_fee_amount;
    }
}