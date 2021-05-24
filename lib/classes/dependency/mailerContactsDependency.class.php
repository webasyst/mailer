<?php

class mailerContactsDependency extends mailerDependency
{
    protected function _callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

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

        if (false !== strpos($value, '/category/')) {
            $category_id = explode('/', $value);
            $category_id = end($category_id);
            if ($category_id && wa_is_int($category_id)) {
                $recipients_groups['categories']['selected'][$r_id] = $category_id;
                $recipients_groups['categories']['opened'] = true;
            } else {
                $recipients_groups['categories']['all_selected_id'] = $r_id;
            }
            return;
        }

        if (false !== strpos($value, '/locale=')) {
            $locale = explode('=', $value);
            $locale = end($locale);
            $recipients_groups['languages']['selected'][$r_id] = $locale;
            $recipients_groups['languages']['opened'] = true;
            return;
        }

        if ($value == '/') {
            $recipients_groups['categories']['all_selected_id'] = $r_id;
            return;
        }
    }

    protected function _callMailerRecipientsFormHandlerAfterMainLoop(&$recipients_groups)
    {
        if (!$this->hasAccess()) {
            return;
        }

        try {
            $action = new mailerCampaignsRecipientsBlockCategoriesAction($recipients_groups['categories']);
            $recipients_groups['categories']['content'] = trim($action->display());
        } catch (Exception $e) {
            // hide categories block when nothing is selected and there are no available categories
            unset($recipients_groups['categories']);
        }

        try {
            $action = new mailerCampaignsRecipientsBlockLanguagesAction($recipients_groups['languages']);
            $recipients_groups['languages']['content'] = trim($action->display());
        } catch (Exception $e) {
            // hide languages group when nothing is selected and there's only one language
            unset($recipients_groups['languages']);
        }
    }

    /**
     * @param &$recipient
     * @return bool If recipient has catch in than expected true returned
     */
    protected function _callHelperGetRecipients(&$recipient)
    {
        if (false !== strpos($recipient['value'], '/category/')) {
            $recipient['short'] = _w('Category');
            $recipient['link'] = wa()->getAppUrl('contacts').'#'.$recipient['value'];
            return true;
        }

        if (false !== strpos($recipient['value'], '/locale=')) {
            $recipient['short'] = _w('Language');
            $recipient['link'] = wa()->getAppUrl('contacts') . '#' . $recipient['value'];
            return true;
        }
    }

    protected function _callMailerRecipientsPrepareHandlerPrepareRecipient(&$recipient)
    {
        $hash = $recipient['value'];

        $cc = new waContactsCollection($hash);

        if (false !== strpos($hash, '/category/')) {
            $category_id = explode('/', $hash);
            $category_id = end($category_id);
            if ($category_id && wa_is_int($category_id)) {
                $recipient['name'] = $this->getCategoryName($category_id);
                $recipient['group'] = _w('Categories');
                $recipient['count'] = $cc->count();
            }
            return true;
        }

        if (false !== strpos($hash, '/locale=')) {
            $locale = explode('=', $hash);
            $locale = end($locale);
            $recipient['name'] = $this->getLocaleName($locale);
            $recipient['group'] = _w('Languages');
            $recipient['count'] = $cc->count();
            return true;
        }
    }


    private function getCategoryName($id)
    {
        static $categories;
        if ($categories === null) {
            $ccm = new waContactCategoryModel();
            $categories = $ccm->getNames();
        }
        return isset($categories[$id]) ? $categories[$id] : $id;
    }

    private function getLocaleName($locale)
    {
        if (!$locale) {
            return _w('not set');
        }
        $info = waLocale::getInfo($locale);
        return $info ? $info['name'] : $locale;
    }
}
