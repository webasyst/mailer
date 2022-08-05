<?php

class mailerSendersConfirmCodeWaController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING);

        $api = new mailerWaTransportServiceApi();
        $this->response = $api->call(
            'ip-white-list/confirm-operation',
            ['code' => $code],
            waNet::METHOD_POST,
            [
                'format' => waNet::FORMAT_JSON,
                'request_format' => waNet::FORMAT_RAW,
            ]
        );
    }
}
