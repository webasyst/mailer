<?php

class mailerContactsProDependencyViewAction extends mailerDependencyViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isContactsPro()) {
            throw new waException(_w('Action supported only when Contacts App with PRO plugin are installed'));
        }
    }
}
