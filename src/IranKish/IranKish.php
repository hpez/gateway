<?php

namespace Shirazsoft\Gateway\IranKish;

use DateTime;
use Illuminate\Support\Facades\Input;
use Shirazsoft\Gateway\Enum;
use SoapClient;
use Shirazsoft\Gateway\PortAbstract;
use Shirazsoft\Gateway\PortInterface;

class IranKish extends PortAbstract implements PortInterface
{
	/**
	 * Address of main SOAP server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://ikc.shaparak.ir/XToken/Tokens.xml';

	/**
	 * {@inheritdoc}
	 */
	public function set($amount)
	{
		$this->amount = $amount;

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function ready()
	{
		$this->sendPayRequest();

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function redirect()
	{
		$refId = $this->refId;

		return view('gateway::irankish-redirector')->with(compact('refId'));
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->userPayment();
		$this->verifyPayment();
		$this->settleRequest();

		return $this;
	}

	/**
	 * Sets callback url
	 * @param $url
	 */
	function setCallback($url)
	{
		$this->callbackUrl = $url;
		return $this;
	}

	/**
	 * Gets callback url
	 * @return string
	 */
	function getCallback()
	{
		if (!$this->callbackUrl)
			$this->callbackUrl = $this->config->get('gateway.irankish.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws IranKishException
	 */
	protected function sendPayRequest()
	{
		$dateTime = new DateTime();

		$this->newTransaction();

		$fields = array(
			'amount' => $this->amount,
			'merchantId' => $this->config->get('gateway.irankish.merchantId'),
			'invoiceNo' => time(),
			'paymentId' => time(),
			'specialPaymentId' => '',
			'revertURL' => $this->getCallback(),
			'description' => ''
		);

		try {
			$soap = new SoapClient($this->serverUrl, array('soap_version'   => SOAP_1_1));
			$response = $soap->MakeToken($fields);

		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}

        dd($response);
        
		/*$response = explode(',', $response->return);

		if ($response[0] != '0') {
			$this->transactionFailed();
			$this->newLog($response[0], IranKishException::$errors[$response[0]]);
			throw new IranKishException($response[0]);
		}
		$this->refId = $response[1];
		$this->transactionSetRefId();*/
	}

	/**
	 * Check user payment
	 *
	 * @return bool
	 *
	 * @throws IranKishException
	 */
	protected function userPayment()
	{
		$this->refId = Input::get('RefId');
		$this->trackingCode = Input::get('SaleReferenceId');
		$this->cardNumber = Input::get('CardHolderPan');
		$payRequestResCode = Input::get('ResCode');

		if ($payRequestResCode == '0') {
			return true;
		}

		$this->transactionFailed();
		$this->newLog($payRequestResCode, @IranKishException::$errors[$payRequestResCode]);
		throw new IranKishException($payRequestResCode);
	}

	/**
	 * Verify user payment from bank server
	 *
	 * @return bool
	 *
	 * @throws IranKishException
	 * @throws SoapFault
	 */
	protected function verifyPayment()
	{
		$fields = array(
			'terminalId' => $this->config->get('gateway.mellat.terminalId'),
			'userName' => $this->config->get('gateway.mellat.username'),
			'userPassword' => $this->config->get('gateway.mellat.password'),
			'orderId' => $this->transactionId(),
			'saleOrderId' => $this->transactionId(),
			'saleReferenceId' => $this->trackingCode()
		);

		try {
			$soap = new SoapClient($this->serverUrl);
			$response = $soap->bpVerifyRequest($fields);

		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}

		if ($response->return != '0') {
			$this->transactionFailed();
			$this->newLog($response->return, IranKishException::$errors[$response->return]);
			throw new IranKishException($response->return);
		}

		return true;
	}

	/**
	 * Send settle request
	 *
	 * @return bool
	 *
	 * @throws IranKishException
	 * @throws SoapFault
	 */
	protected function settleRequest()
	{
		$fields = array(
			'terminalId' => $this->config->get('gateway.mellat.terminalId'),
			'userName' => $this->config->get('gateway.mellat.username'),
			'userPassword' => $this->config->get('gateway.mellat.password'),
			'orderId' => $this->transactionId(),
			'saleOrderId' => $this->transactionId(),
			'saleReferenceId' => $this->trackingCode
		);

		try {
			$soap = new SoapClient($this->serverUrl);
			$response = $soap->bpSettleRequest($fields);

		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}

		if ($response->return == '0' || $response->return == '45') {
			$this->transactionSucceed();
			$this->newLog($response->return, Enum::TRANSACTION_SUCCEED_TEXT);
			return true;
		}

		$this->transactionFailed();
		$this->newLog($response->return, IranKishException::$errors[$response->return]);
		throw new IranKishException($response->return);
	}
}
