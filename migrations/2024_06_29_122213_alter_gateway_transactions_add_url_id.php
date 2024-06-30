<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Hpez\Gateway\Enum;

class AlterGatewayTransactionsAddUrlId extends Migration
{
	function getTable()
	{
		return config('gateway.table', 'gateway_transactions');
	}

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table($this->getTable(), function (Blueprint $table) {
			$table->string('url_id', 250)->nullable()->after('tracking_code');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table($this->getTable(), function (Blueprint $table) {
            $table->dropColumn('url_id');
        });
	}
}
