<?php

class mailerSendersAddIpWaController extends waJsonController
{
    public function execute()
    {
        $ip = waRequest::post('ip', '', waRequest::TYPE_STRING_TRIM);
        $api = new mailerWaTransportServiceApi();
        $this->response = $api->addIpToWhiteList($ip);
    }
}
