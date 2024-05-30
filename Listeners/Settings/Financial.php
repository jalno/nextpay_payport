<?php

namespace packages\nextpay_payport\Listeners\Settings;

use packages\financial\Events\GateWays;
use packages\nextpay_payport\GateWay;

class Financial
{
    public function gateways_list(GateWays $gateways)
    {
        $gateway = new GateWays\GateWay('nextpay');
        $gateway->setHandler(GateWay::class);
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
