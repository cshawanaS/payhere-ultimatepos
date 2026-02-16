<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payhere_module_settings', function (Blueprint $table) {
            $table->string('payment_method')->default('custom_pay_1')->after('pos_account_id');
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
            $table->dropColumn('payment_method');
        });
    }
};
