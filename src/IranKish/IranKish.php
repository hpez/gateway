<?php

namespace Shirazsoft\Gateway\IranKish;

use Illuminate\Support\Facades\Input;
use DateTime;
use SoapClient;
use Shirazsoft\Gateway\PortAbstract;
use Shirazsoft\Gateway\PortInterface;
use Shirazsoft\Gateway\Enum;

class IranKish extends PortAbstract implements PortInterface
{
    /**
     * Address of main server
     *
     * @var string
     */
    protected $serverUrl = 'https://ikc.shaparak.ir/XToken/Tokens.xml';

    /**
     * Address of SOAP server for verify payment
     *
     * @var string
     */
    protected $serverVerifyUrl = 'https://ikc.shaparak.ir/XVerify/Verify.xml';

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
        $merchantId = $this->config->get('gateway.irankish.merchant-id');
        return view('gateway::irankish-redirector')->with([
            'refId' => $this->refId,
            'merchantId' => $merchantId,
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

        return $this;
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
            'merchantId' => $this->config->get('gateway.irankish.merchant-id'),
            'description' => $this->config->get('gateway.irankish.description'),
            'invoiceNo' => $this->transactionId(),
            'paymentId' => $this->transactionId(),
            'specialPaymentId' => $this->transactionId(),
            'revertURL' => $this->getCallback(),
        );

        try {
            $soap = new SoapClient($this->serverUrl, array('soap_version'   => SOAP_1_1));
            $response = $soap->MakeToken($fields);
        } catch(\SoapFault $e) {
            $this->transactionFailed();
            $this->newLog('SoapFault', $e->getMessage());
            throw $e;
        }

        if ($response->MakeTokenResult->result == false) {
            $this->transactionFailed();
            $this->newLog($response->MakeTokenResult->result, $response->MakeTokenResult->message);
            throw new IranKishException;
        }
        $this->refId = $response->MakeTokenResult->token;
        $this->transactionSetRefId($this->transactionId);
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
        $this->refId = Input::get('token');
        $this->trackingCode = Input::get('referenceId');
        $resultCode = Input::get('resultCode');

        if ($resultCode == '100') {
            return true;
        }

        $this->transactionFailed();
        $this->newLog($resultCode, @IranKishException::$errors[$resultCode]);
        throw new IranKishException($resultCode);
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
            'token' => $this->refId,
            'referenceNumber' => $this->trackingCode,
            'merchantId' => $this->config->get('gateway.irankish.merchant-id'),
            'sha1Key' => $this->config->get('gateway.irankish.sha1-key')
        );

        try {
            $soap = new SoapClient($this->serverVerifyUrl);
            $response = $soap->KicccPaymentsVerification($fields);

        } catch(\SoapFault $e) {
            $this->transactionFailed();
            $this->newLog('SoapFault', $e->getMessage());
            throw $e;
        }

        $response = floatval($response->KicccPaymentsVerificationResult);

        if ($response > 0) {
            $this->transactionSucceed();
            $this->newLog('100', Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        } else {
            $this->transactionFailed();
            $this->newLog($response, @IranKishException::$errors[$response]);
            throw new IranKishException($response);
        }
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
            $this->callbackUrl = $this->config->get('gateway.irankish.callback-url');
        }
        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }
}
