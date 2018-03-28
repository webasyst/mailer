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
        $recipients_groups['categories'] = array(
            'name' => _w('Categories'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => _w('Categories are groups of contacts which you can freely manage in the Contacts application. In addition to manually created categories, there are also system categories created by other Webasyst applications; e.g., Shop-Script or Blog. Those categories contain contacts added by the corresponding applications: customers of the online store or authors of comments posted in the blog.'),

            // not part of event interface, but used internally here
            'selected' => array(),
            'all_selected_id' => false
        );

        // move to bottom
        foreach (array('subscribers', 'flat_list') as $key) {
            if (isset($recipients_groups[$key])) {
                $group = $recipients_groups[$key];
                unset($recipients_groups[$key]);
                $recipients_groups[$key] = $group;
            }
        }
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

        if (FALSE !== strpos($value, '/category/')) {
            $category_id = explode('/', $value);
            $category_id = end($category_id);
            if ($category_id && wa_is_int($category_id)) {
                $recipients_groups['categories']['selected'][$r_id] = $category_id;
                $recipients_groups['categories']['opened'] = true;
            } else {
                $recipients_groups['categories']['all_selected_id'] = $r_id;
            }
        } elseif (FALSE !== strpos($value, '/locale=')) {
            $locale = explode('=', $value);
            $locale = end($locale);
            $recipients_groups['languages']['selected'][$r_id] = $locale;
            $recipients_groups['languages']['opened'] = true;
            return;
        }/* elseif ($value == '/') {
            $recipients_groups['categories']['all_selected_id'] = $r_id;
        }*/
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

        try {
            $action = new mailerCampaignsRecipientsBlockCategoriesAction($recipients_groups['categories']);
            $recipients_groups['categories']['content'] = trim($action->display());
        } catch (Exception $e) {
            // hide categories block when nothing is selected and there are no available categories
            unset($recipients_groups['categories']);
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
