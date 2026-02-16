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
        Schema::create('payhere_module_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('business_id')->unsigned();
            $table->string('merchant_id')->nullable();
            $table->string('secret')->nullable();
            $table->string('account_id')->nullable();
            $table->string('mode')->default('sandbox');
            $table->timestamps();

            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payhere_module_settings');
    }
};
