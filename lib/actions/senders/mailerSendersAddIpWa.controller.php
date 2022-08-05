<?php

class mailerSendersAddIpWaController extends waJsonController
{
    public function execute()
    {
        $ip = waRequest::post('ip', '', waRequest::TYPE_STRING_TRIM);
        $api = new mailerWaTransportServiceApi();
        $this->response = $api->call(
            'ip-white-list/add',
            ['ip' => $ip],
            waNet::METHOD_POST,
            [
                'format' => waNet::FORMAT_JSON,
                'request_format' => waNet::FORMAT_RAW,
            ]
        );
    }
}
