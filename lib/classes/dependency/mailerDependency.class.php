<?php

abstract class mailerDependency
{
    protected static $static_cache;
    protected $cache;
    protected $is_admin = false;
    protected $app_id;

    public function __construct()
    {
        if ($this->app_id) {
            $this->is_admin = wa()->getUser()->isAdmin($this->app_id);
        }
    }

    public static function resolve()
    {
        if (waConfig::get('is_template')) {
            self::logResolveError();
            return null;
        }
        if (self::tryResolveDependency('crm')) {
            $d = new mailerCrmDependency();
            $d->app_id = 'crm';
        } elseif (self::tryResolveDependency('contacts.pro')) {
            $d = new mailerContactsProDependency();
            $d->app_id = 'contacts';
        } elseif (self::tryResolveDependency('contacts')) {
            $d = new mailerContactsDependency();
            $d->app_id = 'contacts';
        } else {
            $d = new mailerNullDependency();
        }
        return $d;
    }

    protected static function logResolveError()
    {
        if (version_compare(phpversion(), '5.3.6', '<')) {
            // php version isn't high enough
            $trace = debug_backtrace(false);
        } else {
            $trace = debug_backtrace(~DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        waLog::dump($trace, 'mailer/dependency/abstract/resolve_error_trace.log');
    }

    public function getRights($name, $assoc = true)
    {
        if (!$this->app_id) {
            return false;
        }
        return wa()->getUser()->getRights($this->app_id, $name, $assoc);
    }

    public function hasAccess()
    {
        return $this->getRights('backend') > 0;
    }

    public function isResolved()
    {
        return !($this instanceof mailerNullDependency);
    }

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function isMailerAdmin()
    {
        return wa()->getUser()->isAdmin('mailer');
    }

    public function isSuperAdmin()
    {
        return wa()->getUser()->isAdmin('webasyst');
    }

    public function hasAdminRights()
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public function hasCheckAllContactsRight()
    {
        if ($this->isSuperAdmin() || $this->isMailerAdmin()) {
            return true;
        }
        if ($this->isAdmin() && ($this->app_id == 'crm' || $this->app_id == 'contacts')) {
            return true;
        }
        return false;
    }

    public function isCrm()
    {
        return $this instanceof mailerCrmDependency;
    }

    public function isContactsPro()
    {
        return $this instanceof mailerContactsProDependency;
    }

    public function isContacts()
    {
        return $this instanceof mailerContactsDependency;
    }

    public function call($name, &$params)
    {
        // consume 'mailer' prefix
        if (substr($name, 0, 6) == 'mailer') {
            $name = substr($name, 6);
        }
        $parts = explode('::', $name, 2);
        $parts = array_map(
            wa_lambda('$part', 'return ucfirst($part);'),
            $parts
        );

        $basic_method_name = 'call' . join('', $parts);
        $concrete_method_name = '_call' . join('', $parts);

        return $this->makeProxyCall($basic_method_name, $params, $concrete_method_name);
    }

    private function makeProxyCall($method, &$params, $next_method)
    {
        if (method_exists($this, $next_method)) {
            $result = $this->callMethod($method, $params, $next_method);
        } else {
            $result = $this->callMethod($method, $params);
        }
        if (!$result['call']) {
            $result = $this->callMethod($next_method, $params);
        }
        return $result;
    }

    private function callMethod($method, &$params)
    {
        $result = array(
            'call' => false,
            'return' => null
        );
        if ($method && method_exists($this, $method)) {
            $result['call'] = true;
            $pass = array(&$params);
            $arguments = func_get_args();
            $arguments = array_slice($arguments, 2);
            $pass = array_merge($pass, $arguments);
            $result['return'] = call_user_func_array(array($this, $method), $pass);
        }
        return $result;
    }

    /**
     * @param string $id app_id.plugin_id | app_id, example "contacts", "contacts.pro"
     * @param int|null $level min level of access
     * @return bool success status
     */
    protected static function tryResolveDependency($id, $level = null)
    {
        $id = explode('.', $id, 2);
        $app_id = (string)ifset($id[0]);
        $plugin_id = (string)ifset($id[1]);

        if ($level !== null && $level > wa()->getUser()->getRights($app_id, 'backend')) {
            return false;
        }
        if (!$plugin_id) {
            return wa()->appExists($app_id) && wa($app_id);
        }
        static $dependencies = array();
        if (!isset($dependencies[$app_id][$plugin_id])) {
            $dependencies[$app_id][$plugin_id] = wa()->appExists($app_id) &&
                ($plugins = wa($app_id)->getConfig()->getPlugins()) &&
                !empty($plugins[$plugin_id]);
        }
        return $dependencies[$app_id][$plugin_id] && wa($app_id);
    }

    protected function callHelperGetRecipients(&$recipient, $next_method = null)
    {
        $known = true;
        if (false !== strpos($recipient['value'], '/category/')) {
            $recipient['short'] = _w('Category');
        } elseif (false !== strpos($recipient['value'], '/locale=')) {
            $recipient['short'] = _w('Language');
        } else {
            $known = false;
        }

        if (!$this->hasAccess()) {
            if (!$known) {
                $recipient = null;
            }
            return;
        }

        $result = $this->callMethod($next_method, $recipient);

        // if recipient has catch in $next_method than expected true returned
        if ($result['call']) {
            $known = $result['return'] === true;
        }

        if (!$known) {
            $recipient = null;
        }
    }

    protected function callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups, $next_method = null)
    {
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

        $this->callMethod($next_method, $recipients_groups);

        // move to bottom
        foreach (array('subscribers', 'flat_list') as $key) {
            if (isset($recipients_groups[$key])) {
                $group = $recipients_groups[$key];
                unset($recipients_groups[$key]);
                $recipients_groups[$key] = $group;
            }
        }
    }

    protected function callMailerRecipientsFormHandlerMainLoop(&$params, $next_method = null)
    {
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
        } elseif ($value == '/') {
            $recipients_groups['categories']['all_selected_id'] = $r_id;
        }

        $this->callMethod($next_method, $params);
    }

    protected function callMailerRecipientsFormHandlerAfterMainLoop(&$recipients_groups, $next_method = null)
    {
        try {
            $action = new mailerCampaignsRecipientsBlockCategoriesAction($recipients_groups['categories']);
            $recipients_groups['categories']['content'] = trim($action->display());
        } catch (Exception $e) {
            // hide categories block when nothing is selected and there are no available categories
            unset($recipients_groups['categories']);
        }

        $this->callMethod($next_method, $recipients_groups);
    }

    protected function callMailerRecipientsPrepareHandlerPrepareRecipient(&$recipient, $next_method = null)
    {
        $known = true;
        $hash = $recipient['value'];

        $recipient['name'] = '';
        $recipient['count'] = 0;
        $recipient['group'] = null;

        if (false !== strpos($hash, '/shop_customers')) {
            $recipient['group'] = _w('Shop customers');
        } elseif ($hash == '/') {
            $recipient['name'] = _w('All contacts');
        } elseif (false !== strpos($hash, '/category/')) {
            $category_id = explode('/', $hash);
            $category_id = end($category_id);
            if ($category_id && wa_is_int($category_id)) {
                $recipient['name'] = $this->getCategoryName($category_id);
                $recipient['group'] = _w('Categories');
            }
        } elseif (false !== strpos($hash, '/locale=')) {
            $locale = explode('=', $hash);
            $locale = end($locale);
            $recipient['name'] = $this->getLocaleName($locale);
            $recipient['group'] = _w('Languages');
        } else {
            $known = false;
        }

        if ($known) {
            $cc = new waContactsCollection($hash);
            $recipient['count'] = $cc->count();
            if (strlen($recipient['name']) <= 0) {
                $recipient['name'] = $cc->getTitle();
            }
        }

        if (!$this->hasAccess()) {
            if (!$known) {
                $recipient = null;
            }
            return;
        }

        $result = $this->callMethod($next_method, $recipient);

        // if recipient has catch in $next_method than expected true returned
        if ($result['call']) {
            $known = $result['return'] === true;
        }

        if (!$known) {
            $recipient = null;
        }
    }

    protected function callMailerRecipientsPrepareHandlerUpdateDraftRecipients($params)
    {
        $recipient = $params['recipient'];
        $message_id = $params['message_id'];
        $hash = $recipient['value'];
        $this->getCollection($hash)->saveToTable("mailer_draft_recipients", array(
            'contact_id' => 'id',
            'message_id' => $message_id,
            'name',
            'email' => '_email',
        ), true);
    }

    protected function getCollection($hash)
    {
        return new waContactsCollection($hash);
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
