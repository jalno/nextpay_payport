<?php
namespace packages\nextpay_payport;
use packages\base\{json, http, options};
use packages\financial\payport;
use packages\financial\payport\{gateway as payportGateway, GatewayException, VerificationException, AlreadyVerified, redirect};
use packages\financial\payport_pay;

class gateway extends payportGateway {
	/**
	 * @see https://nextpay.ir/docs/NextPay-WebService-Technical-Guid-V2.0.pdf
	 */
	const GATEWAY = "https://api.nextpay.org/gateway";
	/**
	 * @var string holds nextpay api key
	 */
	private $apiKey;

	/**
	 * @param packages\financial\payport which hold "nextpay_api_key" in Its params.
	 */
	public function __construct(payport $payport){
		$this->apiKey = $payport->param("nextpay_api_key");
		if(!$this->apiKey){
			throw new AuthException;
		}
	}

	/**
	 * Issue a new payment request for given payport_pay object and redirect the client to gateway.
	 * 
	 * @param packages\financial\payport_pay $pay a generated pay which passed by financial package.
	 * @throws packages\RequestException\RequestException when http request failed or responsed "code" not equals to -1
	 * @return packages\financial\payport\redirect for passing client to gateway.
	 */
	public function PaymentRequest(payport_pay $pay){
		$params = array(
			'api_key' => $this->apiKey,
			'order_id' => $pay->id,
			'amount' => $pay->price,
			'callback_uri' => $this->callbackURL($pay),
		);
		$result = $this->sendRequest("token.http", $params);
		if($result['code'] != -1){
			$exception = new RequestException();
			$exception->setParams($params);
			$exception->setResult($result);
			throw $exception; 
		}
		$pay->setParam('nextpay_trans_id', $result['trans_id']);
		$pay->save();
		$redirect = new redirect;
		$redirect->method = redirect::get;
		$redirect->url = self::GATEWAY . "/payment/{$result['trans_id']}";
		return $redirect;
	}

	/**
	 * Verify the payment after return client to system.
	 * 
	 * @param packages\financial\payport_pay $pay holding "nextpay_trans_id" payment
	 * @throws packages\financial\payport\VerificationException for unsuccessfull payments.
	 * @throws packages\financial\payport\AlreadyVerified for duplicate verifications.
	 * @return int new state for payment which can be payport_pay::success or payport_pay::failed
	 */
	public function PaymentVerification(payport_pay $pay){
		$orderID = http::getFormData('order_id');
		$transID = http::getFormData('trans_id');
		if($orderID != $pay->id or $transID != $pay->param('nextpay_trans_id')){
			throw new VerificationException();
		}
		$params = array(
			'api_key' => $this->apiKey,
			'trans_id' => $transID,
			'order_id' => $orderID,
			'amount' => $pay->price,
		);
		$result = $this->sendRequest("verify.http", $params);
		if($result['code'] == -49){
			throw new AlreadyVerified();
		}
		if($result['code'] != 0){
			throw new VerificationException();
		}
		return payport_pay::success;
	}
	/**
	 * Send a post http request to nextpay API gateway and parse returned response.
	 * @param string $path will append to nextpay gateway URL.
	 * @param array $params http body
	 * @throws packages\nextpay_payport\RequestException when http request or json decoding failed.
	 * @return array json decoded response
	 */
	private function sendRequest(string $path, array $params){
		try {
			$http = new http\client();
			$response = $http->post(self::GATEWAY."/".$path, array(
				'cookies' => false,
				'form_params' => $params,
				'ssl_verify' => false,
			));
			$result = json\decode($response->getBody());
			if (!isset($result['code'])) {
				$exception = new RequestException();
				$exception->setParams($params);
				$exception->setResult($response);
				throw $exception;
			}
			return $result;
		}catch(http\responseException $e) {
			$exception = new RequestException();
			$exception->setParams($params);
			$exception->setResult($e->getResponse());
			throw $exception;
		}
	}
}
class AuthException extends GatewayException{}
class RequestException extends GatewayException{
	protected $params;
	protected $result;
	protected $status;
	public function setStatus($status){
		$this->status = $status;
	}
	public function setParams($params){
		$this->params = $params;
	}
	public function setResult($result){
		$this->result = $result;
	}
}
