<?php

class mailerContactsProDependencyJsonController extends mailerDependencyJsonController
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isContactsPro()) {
            throw new waException(_w('Controller supported only when Contacts App with PRO plugin are installed'));
        }
    }
}
