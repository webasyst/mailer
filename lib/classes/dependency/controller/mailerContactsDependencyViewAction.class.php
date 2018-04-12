<?php

class mailerContactsDependencyViewAction extends mailerDependencyViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isContacts()) {
            throw new waException(_w('Action supported only when Contacts App is available (with not PRO plugin installed)'));
        }
    }
}
