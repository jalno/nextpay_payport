<?php

namespace packages\nextpay_payport\listeners\settings;

use packages\financial\events\Gateways;
use packages\nextpay_payport\Gateway;

class financial
{
    public function gateways_list(Gateways $gateways)
    {
        $gateway = new Gateways\Gateway('nextpay');
        $gateway->setHandler(Gateway::class);
        $gateway->addInput([
            'name' => 'nextpay_api_key',
            'type' => 'string',
        ]);
        $gateway->addField([
            'name' => 'nextpay_api_key',
            'label' => t('settings.financial.gateways.nextpay.api_key'),
            'ltr' => true,
        ]);
        $gateways->addGateway($gateway);
    }
}
