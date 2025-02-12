<?php

class mailerSendersBalanceWaController extends waJsonController
{
    public function execute()
    {
        $api = new mailerWaTransportServiceApi();
        $this->response = $api->getBalanceCreditUrl(waServicesApi::EMAIL_MESSAGE_SERVICE);
    }
}
