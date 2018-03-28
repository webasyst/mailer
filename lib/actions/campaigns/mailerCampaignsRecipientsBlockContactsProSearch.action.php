<?php

class mailerCampaignsRecipientsBlockContactsProSearchAction extends waViewAction
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
        $data = array();
        foreach($this->params['selected'] as $id => $hash) {
            $cc = new waContactsCollection($hash);
            $count = $cc->count();
            $title = $cc->getTitle();
            $data[] = array(
                'label' => $title,
                'list_id' => $id,
                'value' => $hash,
                'checked' => true,
                'disabled' => false,
                'num' => $count,
            );
        }
        $this->view->assign('prosearch_data', $data);
        $this->view->assign('all_selected_id', $this->params['all_selected_id']);
    }
}

