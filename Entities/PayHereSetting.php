<?php

namespace Modules\PayHere\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayHereSetting extends Model
{
    protected $table = 'payhere_module_settings';

    protected $fillable = ['business_id', 'merchant_id', 'secret', 'account_id', 'pos_account_id', 'mode', 'payment_method'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'secret' => 'encrypted',
    ];
}
