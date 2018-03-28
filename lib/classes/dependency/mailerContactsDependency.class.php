<?php

class mailerContactsDependency extends mailerDependency
{
    protected function _callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

        $recipients_groups['languages'] = array(
            'name' => _w('Languages'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => _w('Your contacts may be speaking different languages. You can limit the recipient list by selecting only the desired languages.'),

            // not part of event interface, but used internally here
            'selected' => array()
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

        if (false !== strpos($value, '/locale=')) {
            $locale = explode('=', $value);
            $locale = end($locale);
            $recipients_groups['languages']['selected'][$r_id] = $locale;
            $recipients_groups['languages']['opened'] = true;
            return;
        }
    }

    protected function _callMailerRecipientsFormHandlerAfterMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

        try {
            $action = new mailerCampaignsRecipientsBlockLanguagesAction($recipients_groups['languages']);
            $recipients_groups['languages']['content'] = trim($action->display());
        } catch (Exception $e) {
            // hide languages group when nothing is selected and there's only one language
            unset($recipients_groups['languages']);
        }
    }

    protected function _callHelperGetRecipients(&$recipient)
    {
        if (!$this->hasAccess()) {
            return;
        }

        if (FALSE !== strpos($recipient['value'], '/category/')) {
            $recipient['link'] = wa()->getAppUrl('contacts').'#'.$recipient['value'];
        } elseif (FALSE !== strpos($recipient['value'], '/locale=')) {
            $recipient['link'] = wa()->getAppUrl('contacts') . '#' . $recipient['value'];
        }
    }
}
