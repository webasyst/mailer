<?php


class mailerCampaignsRecipientsBlockCrmSegmentsAction extends waViewAction
{
    /**
     * @var mailerCrmDependency
     */
    protected $d;

    public function preExecute()
    {
        $this->d = mailerDependency::resolve();
        if (!$this->d->isCrm()) {
            throw new waException(_w('Crm segments group block supported only when CRM are installed'));
        }
    }

    public function execute()
    {
        $m = new crmSegmentModel();
        $segments = $m->getAllSegments();

        $data = array();
        foreach($segments as $id => $segment) {
            $data[$id] = array(
                'label' => $segment['name'],
                'value' => $id,
                'checked' => false,
                'disabled' => false,
                'num' => $segment['count'] > 0 ? $segment['count'] : '',
            );
        }

        foreach($this->params['selected'] as $id => $segment_id) {
            $data[$segment_id]['checked'] =  true;
            $data[$segment_id]['segment_id'] = $id;
        }

        $this->view->assign('data', $data);
        $this->view->assign('all_selected_id', $this->params['all_selected_id']);
        $this->view->assign('is_admin', $this->d->isAdmin());
    }
}

