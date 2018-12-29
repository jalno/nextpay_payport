<?php
namespace packages\nextpay_payport\listeners\settings;
use \packages\base;
use \packages\base\translator;
use \packages\financial\events\gateways;
class financial{
	public function gateways_list(gateways $gateways){
		$gateway = new gateways\gateway("nextpay");
		$gateway->setHandler('\\packages\\nextpay_payport\\gateway');
		$gateway->addInput(array(
			'name' => 'nextpay_api_key',
			'type' => 'string'
		));
		$gateway->addField(array(
			'name' => 'nextpay_api_key',
			'label' => translator::trans("settings.financial.gateways.nextpay.api_key"),
			'ltr' => true
		));
		$gateways->addGateway($gateway);
	}
}
