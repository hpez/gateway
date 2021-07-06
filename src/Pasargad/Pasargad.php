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
	protected $verifyUrl = 'https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment';
	protected $refundUrl = 'https://pep.shaparak.ir/doRefund.aspx';
	protected $baseUrl = 'https://pep.shaparak.ir/Api/v1/Payment/';
	protected $getTokenUrl = 'GetToken';

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
        header("Location: https://pep.shaparak.ir/payment.aspx?n=" . $this->token);
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
	 * Send pay request to parsian gateway
	 *
	 * @return bool
	 *
	 * @throws ParsianErrorException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();

        $params = [
            'InvoiceNumber' => $this->transactionId(),
            'InvoiceDate' => date("Y/m/d H:i:s"),
            'TerminalCode' => $this->config->get('gateway.pasargad.terminalId'),
            'MerchantCode' => $this->config->get('gateway.pasargad.merchantId'),
            'Amount' => $this->amount,
            'RedirectAddress' => $this->getCallback(),
            'Timestamp' => date("Y/m/d H:i:s"),
            'Action' => 1003,
        ];

        if ($this->cellNumber) {
            $params['mobile'] = $this->cellNumber;
        }

        $data = json_encode($params);

        $processor = new RSAProcessor($this->config->get('gateway.pasargad.certificate-path'), RSAKeyType::XMLFile);
        $data = sha1($data, true);
        $data = $processor->sign($data); // امضاي ديجيتال
        $sign = base64_encode($data); // base64_encode

        $response = $this->client->post($this->getTokenUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Sign' => $sign
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if (!$response->IsSuccess) {
            $this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
            throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
        }

        $this->token = $response->Token;
	}

	/**
	 * Verify payment
	 *
	 * @throws ParsianErrorException
	 */
	protected function verifyPayment()
	{
	    $params = [
            'InvoiceNumber' => $this->transactionId(),
            'MerchantCode' => $this->config->get('gateway.pasargad.merchantId'),
            'TerminalCode' => $this->config->get('gateway.pasargad.terminalId'),
            'InvoiceDate' => Input::get('iD'),
            'Amount' => $this->amount,
            'TimeStamp' => date("Y/m/d H:i:s"),
        ];

		$processor = new RSAProcessor($this->config->get('gateway.pasargad.certificate-path'), RSAKeyType::XMLFile);

		$data = json_encode($params);
		$data = sha1($data, true);
		$data = $processor->sign($data);
		$sign = base64_encode($data);

        $response = $this->client->post($this->verifyUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Sign' => $sign
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

		if (!$response->IsSuccess) {
			$this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
			$this->transactionFailed();
			throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
		}

		$this->refId = $response->ShaparakRefNumber;
		$this->transactionSetRefId();
		$this->trackingCode = Input::get('tref');
		$this->transactionSucceed();
		$this->newLog(0, Enum::TRANSACTION_SUCCEED_TEXT);
	}
}
