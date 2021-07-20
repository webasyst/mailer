<?php

class mailerCrmDependency extends mailerDependency
{
    public function factoryCollection($hash, $options = array())
    {
        return new crmContactsCollection($hash, $options);
    }

    /**
     * @param &$recipient
     * @return bool If recipient has catch in than expected true returned
     */
    protected function _callHelperGetRecipients(&$recipient)
    {
        if (false !== strpos($recipient['value'], '/crm/segment/')) {
            $recipient['short'] = _w('Segment');
            $segment_id = $this->getSegmentIdFromHash($recipient['value']);
            $recipient['link'] = wa()->getAppUrl('crm') . 'contact/segment/' . $segment_id . '/';
            return true;
        }
    }

    protected function _callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }
        $recipients_groups['crm_segments'] = array(
            'name' => _w('CRM segments'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => '',

            // not part of event interface, but used internally here
            'selected' => array(),
            'all_selected_id' => false
        );
    }

    protected function _callMailerRecipientsFormHandlerMainLoop(&$params)
    {
        if (!$this->hasAccess()) {
            return;
        }

        $recipient = $params['recipient'];
        $recipients_groups = &$params['recipients_groups'];

        $value = $recipient['value'];
        $r_id = $recipient['id'];

        if (false !== strpos($value, '/crm/segment/')) {
            $segment_id = $this->getSegmentIdFromHash($value);
            $recipients_groups['crm_segments']['selected'][$r_id] = $segment_id;
            $recipients_groups['crm_segments']['opened'] = true;
            return;
        }
    }

    protected function _callMailerRecipientsFormHandlerAfterMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

        $action = new mailerCampaignsRecipientsBlockCrmSegmentsAction($recipients_groups['crm_segments']);
        $recipients_groups['crm_segments']['content'] = trim($action->display());
    }

    /**
     * @param &$recipient
     * @return bool If recipient has catch in than expected true returned
     */
    protected function _callMailerRecipientsPrepareHandlerPrepareRecipient(&$recipient)
    {
        $hash = $recipient['value'];

        if (false !== strpos($hash, '/crm/segment/')) {
            $m = new crmSegmentModel();
            $segment_id = $this->getSegmentIdFromHash($hash);
            $segment = $m->getSegment($segment_id);
            $recipient['count'] = $this->getCollection($hash)->count();
            $recipient['name'] = $segment['name'];
            $recipient['group'] = _w('CRM Segments');
            return true;
        }
    }

    protected function getCollection($hash)
    {
        if (substr($hash, 0, 5) != '/crm/') {
            return parent::getCollection($hash);
        } else {
            return new crmContactsCollection(substr($hash, 5));
        }
    }

    private function getSegmentIdFromHash($hash)
    {
        $parts = explode('/', $hash);
        $segment_id = end($parts);
        return (int)$segment_id;
    }
}
