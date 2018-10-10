<?php

class mailerContactsDependencyViewAction extends mailerDependencyViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isContacts()) {
            throw new waException(_w('Action is supported only if Contacts app is available, with no PRO plugin installed.'));
        }
    }
}
