<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Shirazsoft\Gateway\PortAbstract;
use Shirazsoft\Gateway\GatewayResolver;
use Shirazsoft\Gateway\Enum;

class CreateGatewayTransactionsTable extends Migration
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
		Schema::create($this->getTable(), function (Blueprint $table) {
			$table->engine = "innoDB";
			$table->unsignedBigInteger('id', true);
			$table->enum('port', [
				Enum::MELLAT,
				Enum::JAHANPAY,
				Enum::PARSIAN,
				Enum::PASARGAD,
				Enum::PAYLINE,
				Enum::SADAD,
				Enum::ZARINPAL,
				Enum::SAMAN,
				Enum::ASANPARDAKHT,
				Enum::PAYPAL,
                Enum::IRANKISH,
                Enum::SADERAT
			]);
			$table->decimal('price', 15, 0);
			$table->string('ref_id', 100)->nullable();
			$table->string('tracking_code', 50)->nullable();
			$table->string('card_number', 50)->nullable();
			$table->enum('status', [
				Enum::TRANSACTION_INIT,
				Enum::TRANSACTION_SUCCEED,
				Enum::TRANSACTION_FAILED,
			])->default(Enum::TRANSACTION_INIT);
			$table->string('ip', 20)->nullable();
			$table->timestamp('payment_date')->nullable();
			$table->nullableTimestamps();
			$table->softDeletes();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop($this->getTable());
	}
}
