<?php

class mailerCrmDependencyViewAction extends mailerDependencyViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!$this->d->isCrm()) {
            throw new waException(_w('Action is supported only if CRM app is installed.'));
        }
    }
}
