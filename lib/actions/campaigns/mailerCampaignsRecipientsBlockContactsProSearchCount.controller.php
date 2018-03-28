<?php

class mailerCampaignsRecipientsBlockContactsProSearchCountController extends waJsonController
{
    /**
     * @var mailerContactsProDependency
     */
    protected $d;

    public function preExecute()
    {
        $this->d = mailerDependency::resolve();
        if (!$this->d->isContactsPro()) {
            throw new waException(_w('Contacts pro search group block supported only when Contacts App with PRO plugin are installed'));
        }
    }

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

