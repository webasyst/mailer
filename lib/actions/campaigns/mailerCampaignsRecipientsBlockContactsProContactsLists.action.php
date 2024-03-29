<?php

/**
 * Content block for recipients selection form.
 * Implements dialog to enter a list of emails.
 */
class mailerCampaignsRecipientsBlockContactsProContactsListsAction extends mailerContactsProDependencyViewAction
{
    public function execute()
    {
        $m = new contactsViewModel();
        $lists = $m->getAllViews();

        $data = array();
        foreach($lists as $id => $list) {
            $data[$id] = array(
                'label' => $list['name'],
                'value' => $id,
                'checked' => false,
                'disabled' => false,
                'num' => $list['count'],
            );
        }


        foreach($this->params['selected'] as $id => $list_id) {
            $data[$list_id]['checked'] =  true;
            $data[$list_id]['list_id'] = $id;
        }

        $this->view->assign('data', $data);
        $this->view->assign('all_selected_id', $this->params['all_selected_id']);
        $this->view->assign('is_admin', $this->d->isAdmin());
    }
}

