<?php

namespace packages\nextpay_payport;

use packages\base\Http;
use packages\base\Json;
use packages\financial\Payport;
use packages\financial\Payport\AlreadyVerified;
use packages\financial\Payport\Gateway as ParentGateway;
use packages\financial\Payport\GatewayException;
use packages\financial\Payport\Redirect;
use packages\financial\Payport\VerificationException;
use packages\financial\Payport_Pay;

class Gateway extends ParentGateway
{
    /**
     * @see https://nextpay.org/nx/docs
     */
    const GATEWAY = 'https://nextpay.org/nx/gateway';

    /**
     * @var string holds nextpay api key
     */
    private $apiKey;

    /**
     * @param payport $payport which hold "nextpay_api_key" in Its params
     */
    public function __construct(Payport $payport)
    {
        $this->apiKey = $payport->param('nextpay_api_key');
        if (!$this->apiKey) {
            throw new GatewayException('There is not api key');
        }
    }

    /**
     * Issue a new payment request for given payport_pay object and redirect the client to gateway.
     *
     * @param payport_pay $pay a generated pay which passed by financial package
     *
     * @throws RequestException when http request failed or responsed "code" not equals to -1
     *
     * @return Redirect for passing client to gateway
     */
    public function paymentRequest(Payport_Pay $pay)
    {
        $params = [
            'api_key' => $this->apiKey,
            'order_id' => $pay->id,
            'amount' => $pay->price,
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
     * @param Payport_Pay $pay holding "nextpay_trans_id" payment
     *
     * @throws VerificationException for unsuccessfull payments
     * @throws AlreadyVerified       for duplicate verifications
     *
     * @return int new state for payment which can be payport_pay::success or payport_pay::failed
     */
    public function PaymentVerification(Payport_Pay $pay)
    {
        $transID = Http::getURIData('trans_id');
        if ($transID != $pay->param('nextpay_trans_id')) {
            throw new VerificationException();
        }
        $params = [
            'api_key' => $this->apiKey,
            'trans_id' => $transID,
            'amount' => $pay->price,
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

        return Payport_pay::success;
    }

    /**
     * Send a post http request to nextpay API gateway and parse returned response.
     *
     * @param string $path   will append to nextpay gateway URL
     * @param array  $params http body
     *
     * @throws RequestException when http request or json decoding failed
     *
     * @return array json decoded response
     */
    private function sendRequest(string $path, array $params)
    {
        try {
            $http = new Http\Client();
            $response = $http->post(self::GATEWAY.'/'.$path, [
                'cookies' => false,
                'form_params' => $params,
                'ssl_verify' => false,
            ]);
            $result = json\decode($response->getBody());
            if (!isset($result['code'])) {
                $exception = new RequestException();
                $exception->setParams($params);
                $exception->setResult($response);
                throw $exception;
            }

            return $result;
        } catch (Http\ResponseException $e) {
            $exception = new RequestException();
            $exception->setParams($params);
            $exception->setResult($e->getResponse());
            throw $exception;
        }
    }
}
