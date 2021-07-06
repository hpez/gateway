<?php

namespace Hpez\Gateway\Saman;

use GuzzleHttp\Client;
use Hpez\Gateway\Enum;
use Illuminate\Support\Facades\Input;
use SoapClient;
use Hpez\Gateway\PortAbstract;
use Hpez\Gateway\PortInterface;

class Saman extends PortAbstract implements PortInterface
{
	/**
	 * Address of main server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://sep.shaparak.ir/OnlinePG/OnlinePG';
	protected $redirectUrl = 'https://sep.shaparak.ir/OnlinePG/SendToken?token=';

	protected $token;

	protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => $this->serverUrl]);
    }

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
        header("Location: $this->redirectUrl" . $this->token);
        die();
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->userPayment();
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
			$this->callbackUrl = $this->config->get('gateway.saman.callback-url');

		$url = $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);

		return $url;
	}

	protected function sendPayRequest()
    {
        $this->newTransaction();

        $params = [
            'Action' => 'Token',
            'Amount' => $this->amount,
            'TerminalId' => $this->config->get('gateway.saman.merchant'),
            'ResNum' => $this->transactionId(),
            'RedirectURL' => $this->getCallback(),

        ];

        if ($this->cellNumber) {
            $params['cellNumber'] = $this->cellNumber;
        }

        $response = $this->client->post('', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response->status != 1) {
            $this->newLog($response->errorCode, $response->errorDesc);
            throw new SamanException(Enum::TRANSACTION_FAILED_TEXT);
        }

        $this->token = $response->Token;
    }

	/**
	 * Check user payment
	 *
	 * @return bool
	 *
	 * @throws SamanException
	 */
	protected function userPayment()
	{
		$this->refId = Input::get('RefNum');
		$this->trackingCode = Input::get('TraceNo');
		$this->cardNumber = Input::get('SecurePan');
		$payRequestRes = Input::get('State');
		$payRequestResCode = Input::get('Status');

		if ($payRequestRes == 'OK') {
			return true;
		}

		$this->transactionFailed();
		$this->newLog($payRequestResCode, @SamanException::$errors[$payRequestRes]);
		throw new SamanException($payRequestRes);
	}


	/**
	 * Verify user payment from bank server
	 *
	 * @return bool
	 *
	 * @throws SamanException
	 */
	protected function verifyPayment()
	{
		$params = [
			"merchantID" => $this->config->get('gateway.saman.merchant'),
			"RefNum" => $this->refId,
		];

        $response = $this->client->post('/verifyTxnRandomSessionkey/ipg/VerifyTransaction', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $params
        ]);

        $response = json_decode($response->getBody()->getContents());

        if ($response != $this->amount) {
			$this->transactionFailed();
			$this->newLog($response, SamanException::$errors[$response]);
			throw new SamanException($response);
		}

		$this->transactionSucceed();

		return true;
	}


}
