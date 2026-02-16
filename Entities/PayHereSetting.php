<?php

namespace Modules\PayHere\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayHereSetting extends Model
{
    protected $table = 'payhere_module_settings';

    protected $fillable = [
        'business_id', 
        'merchant_id', 
        'secret', 
        'account_id', 
        'pos_account_id', 
        'mode', 
        'payment_method',
        'fee_percentage',
        'max_fee_amount',
        'enable_fee'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'secret' => 'encrypted',
        'fee_percentage' => 'float',
        'max_fee_amount' => 'float',
        'enable_fee' => 'boolean',
    ];
    
    /**
     * Default values for attributes
     */
    protected $attributes = [
        'fee_percentage' => 3.00,
        'max_fee_amount' => 0,
        'enable_fee' => true,
    ];
}
