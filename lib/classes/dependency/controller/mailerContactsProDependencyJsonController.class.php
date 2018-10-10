<?php

class mailerContactsProDependencyJsonController extends mailerDependencyJsonController
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isContactsPro()) {
            throw new waException(_w('Controller is supported only if Contacts app and PRO plugin are installed.'));
        }
    }
}
