<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Using raw statement to avoid DBAL dependency for change()
        DB::statement('ALTER TABLE payhere_module_settings MODIFY secret TEXT');

        // Truncate the table because existing keys are truncated/invalid
        DB::table('payhere_module_settings')->truncate();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
