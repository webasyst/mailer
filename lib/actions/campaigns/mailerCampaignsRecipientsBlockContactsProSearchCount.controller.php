<?php

class mailerCampaignsRecipientsBlockContactsProSearchCountController extends mailerContactsProDependencyJsonController
{
    public function execute()
    {
        $hash = waRequest::post('hash');
        $count = 0;
        if($hash) {
            $cc = new waContactsCollection($hash);
            $count = $cc->count();
        }
        else {
            $this->errors = "no hash";
        }
        if (!$count) {
            $this->errors = "no contacts";
        }
        else {
            $this->response = $count;
        }
    }
}

