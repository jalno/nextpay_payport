<?php

namespace packages\nextpay_payport;

use packages\financial\PayPort\GateWayException;

class RequestException extends GateWayException
{
    protected $params;
    protected $result;
    protected $status;

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function setResult($result)
    {
        $this->result = $result;
    }
}
