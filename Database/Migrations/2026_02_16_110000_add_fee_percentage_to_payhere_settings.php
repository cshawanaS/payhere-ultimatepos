<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFeePercentageToPayhereSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payhere_module_settings', function (Blueprint $table) {
            $table->decimal('fee_percentage', 5, 2)->default(3.00)->nullable()->after('payment_method');
            $table->decimal('max_fee_amount', 12, 2)->default(0)->nullable()->after('fee_percentage');
            $table->boolean('enable_fee')->default(false)->nullable()->after('max_fee_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payhere_module_settings', function (Blueprint $table) {
            $table->dropColumn(['fee_percentage', 'max_fee_amount', 'enable_fee']);
        });
    }
}
