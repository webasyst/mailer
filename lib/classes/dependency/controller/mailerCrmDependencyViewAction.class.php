<?php

class mailerCrmDependencyViewAction extends mailerDependencyViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isCrm()) {
            throw new waException(_w('Action supported only when CRM are installed'));
        }
    }
}
