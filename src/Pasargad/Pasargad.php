<?php

namespace Hpez\Gateway\Pasargad;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Input;
use Hpez\Gateway\Enum;
use SoapClient;
use Hpez\Gateway\PortAbstract;
use Hpez\Gateway\PortInterface;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;

class Pasargad extends PortAbstract implements PortInterface
{
	/**
	 * Url of parsian gateway web service
	 *
	 * @var string
	 */

	protected $checkTransactionUrl = 'https://pep.shaparak.ir/CheckTransactionResult.aspx';
	protected $verifyUrl = 'api/payment/verify-transactions';
	protected $refundUrl = 'https://pep.shaparak.ir/doRefund.aspx';
	protected $baseUrl = 'https://pep.shaparak.ir/dorsa1/';
	protected $getTokenUrl = 'token/getToken';
	protected $purchaseUrl = 'api/payment/purchase';
	protected $redirectUrl = '';

	protected $client;

	protected $token;

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://pep.shaparak.ir/gateway.aspx';

	public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    /**
	 * {@inheritdoc}
	 */
	public function set($amount)
	{
		$this->amount = intval($amount);
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
        header("Location: " . $this->redirectUrl);
        die();
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
		if (!$this->callbackUrl) {
			$this->callbackUrl = $this->config->get('gateway.pasargad.callback-url');
		}
		return $this->callbackUrl;
	}

    /**
     * @return void
     * @throws PasargadErrorException
     */
    protected function login()
    {
        $response = $this->client->post($this->getTokenUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $this->config->get('gateway.pasargad.username'),
                'password' => $this->config->get('gateway.pasargad.password')
            ]
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (!isset($response->resultCode) || $response->resultCode !== 0) {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
        }

        $this->token = $response->token;
    }

	/**
	 * Send pay request to parsian gateway
	 *
	 * @return bool
	 *
	 * @throws PasargadErrorException
	 */
	protected function sendPayRequest()
	{
        $this->login();

		$this->newTransaction();

        $params = [
            'amount' => $this->amount,
            'callbackApi' => $this->getCallback(),
            'description' => ' ',
            'invoice' => $this->transactionId(),
            'invoiceDate' => date("Y/m/d H:i:s"),
            'serviceCode' => 8,
            'serviceType' => 'PURCHASE',
            'terminalNumber' => $this->config->get('gateway.pasargad.terminalId')
        ];

        if ($this->cellNumber) {
            $params['mobileNumber'] = $this->cellNumber;
        }

        $response = $this->client->post($this->purchaseUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $this->token"
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (!isset($response->resultCode) || $response->resultCode !== 0) {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
        }

        $this->urlId = $response->data->urlId;
        $this->transactionSetUrlId();
        $this->redirectUrl = $response->data->url;

        return true;
	}

	/**
	 * Verify payment
	 *
	 * @throws PasargadErrorException
	 */
	protected function verifyPayment()
	{
        $this->login();

	    $params = [
            'invoice' => (string)$this->transactionId(),
            'urlId' => $this->urlId,
        ];

        $response = $this->client->post($this->verifyUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $this->token"
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (!isset($response->resultCode) || $response->resultCode !== 0) {
			$this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
			$this->transactionFailed();
			throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
		}

		$this->refId = Input::get('referenceNumber');
		$this->transactionSetRefId();
		$this->trackingCode = Input::get('trackId');
		$this->transactionSucceed();
		$this->newLog(0, Enum::TRANSACTION_SUCCEED_TEXT);
	}
}
