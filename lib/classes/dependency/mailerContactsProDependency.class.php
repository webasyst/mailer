<?php

class mailerContactsProDependency extends mailerDependency
{

    /**
     * @param &$recipient
     * @return bool If recipient has catch in than expected true returned
     */
    protected function _callHelperGetRecipients(&$recipient)
    {
        if (false !== strpos($recipient['value'], '/contacts/view/')) {
            $recipient['short'] = _w('List');
            $recipient['link'] = wa()->getAppUrl('contacts') . '#' . $recipient['value'];
            return true;
        }

        if (false !== strpos($recipient['value'], '/contacts/prosearch/')) {
            $recipient['short'] = _w('Search');
            $url = wa()->getAppUrl('contacts') . '#' . $recipient['value'] . '/1/';
            $recipient['link'] = str_replace('/contacts/prosearch/', '/contacts/search/', $url);
            return true;
        }
    }

    protected function _callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }
        $recipients_groups['contacts_lists'] = array(
            'name' => _w('Contact lists'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => '',

            // not part of event interface, but used internally here
            'selected' => array(),
            'all_selected_id' => false
        );
        $recipients_groups['prosearch'] = array(
            'name' => _w('Contact search'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => "comment",

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

        if (false !== strpos($value, '/contacts/view/')) {
            $category_list_id = explode('/', $value);
            $category_list_id = end($category_list_id);
            if ($category_list_id && wa_is_int($category_list_id)) {
                $recipients_groups['contacts_lists']['selected'][$r_id] = $category_list_id;
                $recipients_groups['contacts_lists']['opened'] = true;
            } else {
                $recipients_groups['contacts_lists']['all_selected_id'] = $r_id;
            }
            return;
        }

        if (false !== strpos($value, '/contacts/prosearch/')) {
            $recipients_groups['prosearch']['selected'][$r_id] = $value;
            $recipients_groups['prosearch']['opened'] = true;
            return;
        }

    }

    protected function _callMailerRecipientsFormHandlerAfterMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

        $recipients_groups['prosearch']['content'] = contactsHelper::getSearchForm();
        if (!$recipients_groups['prosearch']['content']) {
            unset($recipients_groups['prosearch']);
        }
        $action = new mailerCampaignsRecipientsBlockContactsProContactsListsAction($recipients_groups['contacts_lists']);
        $recipients_groups['contacts_lists']['content'] = trim($action->display());
    }

    /**
     * @param &$recipient
     * @return bool If recipient has catch in than expected true returned
     */
    protected function _callMailerRecipientsPrepareHandlerPrepareRecipient(&$recipient)
    {
        $hash = $recipient['value'];

        $cc = new waContactsCollection($hash);

        if (false !== strpos($hash, '/contacts/view/')) {
            $contacts_list_id = explode('/', $hash);
            $contacts_list_id = end($contacts_list_id);

            $ccm = new contactsViewModel();
            $list = $ccm->get($contacts_list_id);
            $recipient['name'] = $list['name'];
            $recipient['count'] = $cc->count();
            $recipient['group'] = _w('Contacts Lists');
            return true;
        }

        if (false !== strpos($hash, '/contacts/prosearch')) {
            $recipient['count'] = $cc->count();
            $recipient['group'] = _w('Contacts PRO');
            return true;
        }
    }

}
