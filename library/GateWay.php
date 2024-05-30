<?php

namespace packages\nextpay_payport;

use packages\base\Exception;
use packages\base\HTTP;
use packages\base\Json;
use packages\financial\PayPort;
use packages\financial\PayPort\AlreadyVerified;
use packages\financial\PayPort\GateWay as ParentGateWay;
use packages\financial\PayPort\GateWayException;
use packages\financial\PayPort\Redirect;
use packages\financial\PayPort\VerificationException;
use packages\financial\PayPortPay;

class GateWay extends ParentGateWay
{
    /**
     * @see https://nextpay.org/nx/docs
     */
    public const GATEWAY = 'https://nextpay.org/nx/gateway';

    /**
     * @var string holds nextpay api key
     */
    private $apiKey;

    /**
     * @param PayPort $payport which hold "nextpay_api_key" in Its params
     */
    public function __construct(PayPort $payport)
    {
        $this->apiKey = $payport->param('nextpay_api_key');
        if (!$this->apiKey) {
            throw new GateWayException('There is not api key');
        }
    }

    /**
     * Issue a new payment request for given PayPortPay object and redirect the client to gateway.
     *
     * @param PayPortPay $pay a generated pay which passed by financial package
     *
     * @return Redirect for passing client to gateway
     *
     * @throws RequestException when http request failed or responsed "code" not equals to -1
     */
    public function paymentRequest(PayPortPay $pay)
    {
        $params = [
            'api_key' => $this->apiKey,
            'order_id' => $pay->id,
            'amount' => $pay->price,
            'currency' => $this->getPaymentCurrency($pay),
            'callback_uri' => $this->callbackURL($pay),
        ];
        $result = $this->sendRequest('token', $params);
        if (-1 != $result['code']) {
            $exception = new RequestException();
            $exception->setParams($params);
            $exception->setResult($result);
            throw $exception;
        }
        $pay->setParam('nextpay_trans_id', $result['trans_id']);
        $pay->save();
        $redirect = new Redirect();
        $redirect->method = Redirect::get;
        $redirect->url = self::GATEWAY."/payment/{$result['trans_id']}";

        return $redirect;
    }

    /**
     * Verify the payment after return client to system.
     *
     * @param PayPortPay $pay holding "nextpay_trans_id" payment
     *
     * @return int new state for payment which can be PayPortPay::success or PayPortPay::failed
     *
     * @throws VerificationException for unsuccessfull payments
     * @throws AlreadyVerified       for duplicate verifications
     */
    public function PaymentVerification(PayPortPay $pay)
    {
        $transID = HTTP::getURIData('trans_id');
        if ($transID != $pay->param('nextpay_trans_id')) {
            throw new VerificationException();
        }
        $params = [
            'api_key' => $this->apiKey,
            'trans_id' => $transID,
            'amount' => $pay->price,
            'currency' => $this->getPaymentCurrency($pay),
        ];
        $result = $this->sendRequest('verify', $params);
        if (-49 == $result['code']) {
            throw new AlreadyVerified();
        }
        if (0 != $result['code']) {
            throw new VerificationException();
        }

        foreach (['card_holder', 'Shaparak_Ref_Id', 'customer_phone'] as $key) {
            if (isset($result[$key]) and $result[$key]) {
                $pay->setParam('nextpay_'.$key, $result[$key]);
            }
        }

        return PayPortPay::success;
    }

    protected function getPaymentCurrency(PayPortPay $pay): string
    {
        if (in_array($pay->currency->title, ['IRR', 'Rial', 'ریال'])) {
            return 'IRR';
        }
        if (in_array($pay->currency->title, ['IRT', 'Toman', 'تومان'])) {
            return 'IRT';
        }
        throw new Exception('Unknown payment currency');
    }

    /**
     * Send a post http request to nextpay API gateway and parse returned response.
     *
     * @param string $path   will append to nextpay gateway URL
     * @param array  $params http body
     *
     * @return array json decoded response
     *
     * @throws RequestException when http request or json decoding failed
     */
    private function sendRequest(string $path, array $params)
    {
        try {
            $http = new HTTP\Client();
            $response = $http->post(self::GATEWAY.'/'.$path, [
                'cookies' => false,
                'form_params' => $params,
                'ssl_verify' => false,
            ]);
            $result = Json\Decode($response->getBody());
            if (!isset($result['code'])) {
                $exception = new RequestException();
                $exception->setParams($params);
                $exception->setResult($response);
                throw $exception;
            }

            return $result;
        } catch (HTTP\ResponseException $e) {
            $exception = new RequestException();
            $exception->setParams($params);
            $exception->setResult($e->getResponse());
            throw $exception;
        }
    }
}
