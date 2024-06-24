<?php

namespace Hpez\Gateway\Asanpardakht;

use Hpez\Gateway\Enum;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Input;
use SoapClient;
use Hpez\Gateway\PortAbstract;
use Hpez\Gateway\PortInterface;

class Asanpardakht extends PortAbstract implements PortInterface
{
	/**
	 * Address of main SOAP server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://services.asanpardakht.net/paygate/merchantservices.asmx?wsdl';
    protected $verifyUrl = 'Api/v1/Verify';
    protected $getTokenUrl = 'Api/v1/Token';
    protected $baseUrl = 'https://asan.shaparak.ir/';
    protected $tranResultUrl = 'Api/v1/Payment/';

    protected $token;
    protected $payGateTranId;

    /**
	 * {@inheritdoc}
	 */
    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

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

		return view('gateway::asan-pardakht-redirector')->with([
			'refId' => $this->refId
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

        $this->userPayment();
		$this->verifyPayment();
		$this->settlePayment();

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
			$this->callbackUrl = $this->config->get('gateway.asanpardakht.callback-url');

		$url = $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);

		return $url;
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws AsanpardakhtException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();

        $params = [
            "merchantConfigurationId" => $this->config->get('gateway.asanpardakht.merchantConfigId'),
            "serviceTypeId" => 1,
            "localInvoiceId" => $this->transactionId(),
            "amountInRials"=> $this->amount,
            "localDate"=> date("Ymd His"),
            "additionalData" => "",
            "callbackURL" => $this->getCallback(),
            "paymentId" => "",
        ];

        $response = $this->client->post($this->getTokenUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $this->config->get('gateway.asanpardakht.username'),
                'pwd' => $this->config->get('gateway.asanpardakht.password')
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status && $response->status != 200) {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            throw new AsanpardakhtException($response->status);
        }

        $this->token = $response->Token;
    }

    /**
	 * Verify payment from bank server
	 *
	 * @return bool
	 *
	 * @throws AsanpardakhtException
	 */
	protected function verifyPayment()
	{
        $username = $this->config->get('gateway.asanpardakht.username');
        $password = $this->config->get('gateway.asanpardakht.password');

        $response = $this->client->get($this->verifyUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $username,
                'pwd' => $password
            ],
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status != '200') {
            $this->transactionFailed();
            $this->newLog($response->status, AsanpardakhtException::getMessageByCode($response->status));
            throw new AsanpardakhtException($response->status);
        }

        return true;
	}

    /**
     * Settle user payment from bank server
     *
     * @return bool
     *
     * @throws AsanpardakhtException
     */
    protected function settlePayment()
    {
        $username = $this->config->get('gateway.asanpardakht.username');
        $password = $this->config->get('gateway.asanpardakht.password');

        $params = [
            'merchantConfigurationID' => $this->config->get('gateway.asanpardakht.merchantConfigId'),
            'LocalInvoiceId' => $this->transactionId(),
        ];

        $response = $this->client->get($this->tranResultUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $username,
                'pwd' => $password
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status != '200') {
            //If fail, shaparak automatically do it in next 12 houres.
        }

        $this->transactionSucceed();

        return true;
    }

    /**
     * Check user payment
     *
     * @return bool
     *
     * @throws AsanpardakhtException
     */
    protected function userPayment()
    {
        $ReturningParams = $this->tranResult();

        if ($ReturningParams->status && $ReturningParams->status != '200') {
            $this->transactionFailed();
            $this->newLog($ReturningParams->status, AsanpardakhtException::getMessageByCode($ReturningParams->status));
            throw new AsanpardakhtException($ReturningParams->status);
        }

        return true;
    }

    /**
     * Get transaction result
     *
     * @return bool
     *
     * @throws AsanpardakhtException
     */
    protected function tranResult()
    {
        $username = $this->config->get('gateway.asanpardakht.username');
        $password = $this->config->get('gateway.asanpardakht.password');

        $params = [
            'merchantConfigurationID' => $this->config->get('gateway.asanpardakht.merchantConfigId'),
            'LocalInvoiceId' => $this->transactionId(),
        ];

        $response = $this->client->get($this->tranResultUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $username,
                'pwd' => $password
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (($response->status && $response->status != '200') || $response->amount != $this->amount) {
            $this->transactionFailed();
            $this->newLog($response->status, AsanpardakhtException::getMessageByCode($response->status));
            throw new AsanpardakhtException($response->status);
        }

        $this->refId = $response->refId;
        $this->payGateTranId = $response->payGateTranID;
        $this->trackingCode = $response->payGateTranID;
        $this->cardNumber = $response->cardNumber;

        return $response;
    }

    /**
     * Reverse transaction
     *
     * @return bool
     *
     * @throws AsanpardakhtException
     */
    protected function revers()
    {
        $returningParams = $this->tranResult();

        $params = [
            "merchantConfigurationId" => $this->config->get('gateway.asanpardakht.merchantConfigId'),
            "payGateTranId" => $returningParams['payGateTranId'],
        ];

        $response = $this->client->post('v1/Reverse', [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $this->config->get('gateway.asanpardakht.username'),
                'pwd' => $this->config->get('gateway.asanpardakht.password')
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status && $response->status != 200) {
            $this->newLog($response->status, AsanpardakhtException::getMessageByCode($response->status));
            throw new AsanpardakhtException($response->status);
        }

        return true;
    }

    /**
     * Cancel transaction
     *
     * @return bool
     *
     * @throws AsanpardakhtException
     */
    protected function cancel()
    {
        $returningParams = $this->tranResult();

        $params = [
            "merchantConfigurationId" => $this->config->get('gateway.asanpardakht.merchantConfigId'),
            "payGateTranId" => $returningParams['payGateTranId'],
        ];

        $response = $this->client->post('v1/Cancel', [
            'headers' => [
                'Content-Type' => 'application/json',
                'usr' => $this->config->get('gateway.asanpardakht.username'),
                'pwd' => $this->config->get('gateway.asanpardakht.password')
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status && $response->status != 200) {
            $this->newLog($response->status, AsanpardakhtException::getMessageByCode($response->status));
            throw new AsanpardakhtException($response->status);
        }

        return true;
    }
}