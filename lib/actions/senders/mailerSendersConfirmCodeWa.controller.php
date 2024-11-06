<?php

class mailerSendersConfirmCodeWaController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING);
        $api = new mailerWaTransportServiceApi();
        $this->response = $api->confirmIpWhiteListChange($code);
    }
}
