<?php

namespace Hpez\Gateway\Zibal;

use DateTime;
use Illuminate\Support\Facades\Input;
use Hpez\Gateway\Enum;
use SoapClient;
use Hpez\Gateway\PortAbstract;
use Hpez\Gateway\PortInterface;
use GuzzleHttp\Client;

class Zibal extends PortAbstract implements PortInterface
{
	/**
	 * Baseurl of server
	 *
	 * @var string
	 */
	protected $serverUrl = "https://gateway.zibal.ir/v1/";

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://gateway.zibal.ir/start/';

	/**
	 * Payment Description
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Payer Mobile Number
	 *
	 * @var string
	 */
	protected $mobileNumber;

	protected $client;

	protected $trackId;

	public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => $this->serverUrl]);
    }

	public function boot()
	{}

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
		return \Redirect::to($this->gateUrl . $this->trackId);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->verifyPayment();

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
			$this->callbackUrl = $this->config->get('gateway.zarinpal.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws ZarinpalException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();

		$params = array(
			'merchant' => $this->config->get('gateway.zibal.merchant-id'),
			'amount' => $this->amount,
			'callbackUrl' => $this->getCallback(),
			'description' => $this->description ? $this->description : '',
			'orderId' => $this->transactionId()
		);

		if ($this->mobileNumber) {
            $params['mobile'] = $this->mobileNumber;
        }

        $response = $this->client->post('request', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (!isset($response->result) || $response->result !== 100) {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            throw new ZibalException(Enum::TRANSACTION_FAILED_TEXT, $response->result);
        }

        $this->trackId = $response->trackId;
        
        return true;
	}

	/**
	 * Verify user payment from zarinpal server
	 *
	 * @return bool
	 *
	 * @throws ZarinpalException
	 */
	protected function verifyPayment()
	{
		$trackId = Input::get('trackId');
		$params = [
            'merchant' => $this->config->get('gateway.zibal.merchant-id'),
            'trackId' => $trackId,
        ];

        $response = $this->client->post('verify', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents(), false);

        if (!isset($response->result) || $response->result !== 100) {
			$this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
			$this->transactionFailed();
			throw new ZibalException(Enum::TRANSACTION_FAILED_TEXT, $response->result);
		}

		$this->refId = $response->refNumber;
		$this->transactionSetRefId();
		$this->trackingCode = $trackId;
		$this->transactionSucceed();
		$this->newLog(0, Enum::TRANSACTION_SUCCEED_TEXT);
		return true;
	}

	/**
	 * Set Description
	 *
	 * @param $description
	 * @return void
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}

	/**
	 * Set Payer Mobile Number
	 *
	 * @param $number
	 * @return void
	 */
	public function setMobileNumber($number)
	{
		$this->mobileNumber = $number;
	}
}