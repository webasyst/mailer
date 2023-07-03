<?php

/**
 * Class resolves dependency logic OF special blocks of Mailer code
 * Special blocks are blocks that MUST be processed differently
 *
 * In different places of Mailer code, especially related with mailer_recipients processing,
 * MUST be different reaction (logic) that depends on WHAT apps (+plugin) (that work with contacts) are installed (and works)
 *
 * Class uses loose coupling approach - something like 'event'-handling, but NOT waEvent, just PROXY calling delegation
 *
 * Common scheme of work
 *
 * Mailer code construct object in place of depended code block
 *
 * $d = mailerDependency::resolve(); // NOW $d IS CONCRETE INSTANCE of dependency handler (Eg - mailerCrmDependency)
 *
 * Than mailer code call ->CALL method with ID OF CODE-block (You may use __METHOD__ or something like this)
 *
 * $d->call(__METHOD__, ...) // ... - OTHER needed params of code-block (function arguments)
 *
 * And $d NOW magically and loosely process DEPENDED logic
 * Inside it may process in 2 layers:
 *   * BASIC layer - mailerDependency
 *   * CONCRETE layer - (mailerCrmDependency/mailerContactsProDependency/mailerContactsDependency etc)
 *
 * Class mailerDependency
 */
abstract class mailerDependency
{
    protected static $static_cache;
    protected $cache;
    protected $is_admin = false;
    protected $app_id;

    // set to true in case of debug
    protected $debug = false;

    public function __construct()
    {
        if ($this->app_id) {
            $this->is_admin = wa()->getUser()->isAdmin($this->app_id);
        }
    }

    /**
     * Resolve dependency
     * Choose concrete dependency-handler class and construct its object
     *
     * In template environment ALWAYS returns NULL - Secure reasons
     *
     * @return mailerContactsDependency|mailerContactsProDependency|mailerCrmDependency|mailerNullDependency|mailerDependency|null
     *
     * Return CONCRETE class of mailerDependency TYPE or NULL in 'template' environment
     *
     */
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
        self::logTrace($trace, __METHOD__);
    }

    /**
     * Check rights - can WE do something if CURRENT CONCRETE DEPENDENCY handler using ( chosen by resolver - mailerDependency::resolve() )
     *
     * @param $name
     * @param bool $assoc
     * @return array|bool|int
     */
    public function getRights($name, $assoc = true)
    {
        if (!$this->app_id) {
            return false;
        }
        return wa()->getUser()->getRights($this->app_id, $name, $assoc);
    }

    /**
     * Has access to backend if we using CURRENT CONCRETE DEPENDENCY handler ( chosen by resolver - mailerDependency::resolve() )
     * @return bool
     */
    public function hasAccess()
    {
        return $this->getRights('backend') > 0;
    }

    /**
     * Is admin of underline app if we using CURRENT CONCRETE DEPENDENCY handler ( chosen by resolver - mailerDependency::resolve() )
     * @return bool
     */
    public function isAdmin()
    {
        return $this->is_admin;
    }

    /**
     * Is was resolving success anyway?
     * @return bool
     */
    public function isResolved()
    {
        return !($this instanceof mailerNullDependency);
    }

    /**
     * If admin of Mailer
     * @return bool
     */
    public function isMailerAdmin()
    {
        return wa()->getUser()->isAdmin('mailer');
    }

    /**
     * If admin of all Webasyst backend
     * @return bool
     */
    public function isSuperAdmin()
    {
        return wa()->getUser()->isAdmin('webasyst');
    }

    /**
     * If admin of any type
     * @return bool
     */
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
        $original_name = $name;

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

        if ($this->debug) {
            self::logDebug(array(
                'original_callee' => $original_name,
                'basic_handler' => $basic_method_name,
                'concrete_handler' => $concrete_method_name,
                'params' => $params
            ), get_class($this));
        }

        return $this->makeProxyCall($basic_method_name, $params, $concrete_method_name, $original_name);
    }

    private function makeProxyCall($method, &$params, $next_method, $original_name)
    {
        if (method_exists($this, $next_method)) {
            $result = $this->callMethod($method, $params, $next_method, $original_name);
        } else {
            $result = $this->callMethod($method, $params, $original_name);
        }
        if (!$result['call']) {
            $result = $this->callMethod($next_method, $params, $original_name);
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
        if (!$this->hasAccess()) {
            $recipient = null;
            return;
        }

        $result = $this->callMethod($next_method, $recipient);

        // if recipient has catch in $next_method than expected true returned
        if ($result['call']) {
            if ($result['return'] !== true) {
                $recipient = null;
            }
        }

    }

    protected function callMailerRecipientsFormHandlerBeforeMainLoop(&$recipients_groups, $next_method = null)
    {
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

    /**
     * Inside of this common handler it can be called concrete handler with name _callMailerRecipientsPrepareHandlerPrepareRecipient
     *
     * _callMailerRecipientsPrepareHandlerPrepareRecipient must be return TRUE if it consume and process own recipient
     *
     * @see call
     * @see callMethod
     *
     * For debug print func_get_args()
     *
     *
     * @param $recipient
     * @param null $next_method
     *
     * @notice: In case of debugging:
     *      In last argument - func_get_arg(func_num_args() - 1) - is always original name of original callee
     *      Original callee - it is code that call mailerDependency manager
     */
    protected function callMailerRecipientsPrepareHandlerPrepareRecipient(&$recipient, $next_method = null)
    {
        // Flag that represent that somebody of catchers knows about that recipient and can extract info about it
        $known = true;

        // Hash of recipient
        $hash = $recipient['value'];

        // If is specific recipient that holds all contacts hash
        $is_all_contacts = false;

        // so reset recipient info
        $recipient['name'] = '';
        $recipient['count'] = 0;
        $recipient['group'] = null;

        if (false !== strpos($hash, '/shop_customers')) {
            // Shop customer hash - this catcher (dependency level) knows about it
            $recipient['group'] = _w('Store customers');
            $recipient['name'] = _w('Store customers');
        } elseif ($hash == '/') {
            // All contacts hash - this catcher (dependency level) knows about it
            $recipient['name'] = _w('All contacts');
            $is_all_contacts = true;
        } else {
            // This catcher doesn't know about this recipient
            $known = false;
        }

        // If this catcher knows about recipient - extract info
        if ($known) {
            $cc = $this->getCollection($hash);
            $recipient['count'] = $cc->count();
            if (strlen($recipient['name']) <= 0) {
                $recipient['name'] = $cc->getTitle();
            }
        }

        // For all contacts not need to pass process to concrete dependency handler (by callMethod)
        if ($is_all_contacts) {
            return;
        }

        // This level of dependency attached to some app, and if current user has not access to this app - catcher stops asking about recipient
        if (!$this->hasAccess()) {
            if (!$known) {
                // Don't known what recipient it is, so reset to NULL
                $recipient = null;
            }
            return;
        }

        // Catcher now can try ask "Next method" about recipient
        // Even if we already know something about current recipient - in case if next_method knows about recipient more
        $result = $this->callMethod($next_method, $recipient);

        // $next_method actually response something (next level in dependency chain was actually called)
        if ($result['call']) {

            // if recipient has catch in $next_method than expected TRUE returned
            // TRUE means next_method know about recipient and it extract some info about it
            if ($result['return'] === true) {
                $known = true;
            }

            // If TRUE hasn't been returned than $known flag stay own values as it is

        }

        if (!$known) {
            // Don't known what recipient it is, so reset to NULL
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
        return new waContactsCollection($hash, array(
            'transform_phone_prefix' => 'all_domains'
        ));
    }


    protected static function logTrace($trace, $callee)
    {
        $callee = str_replace('::', '/', $callee);
        waLog::dump($trace, "mailer/dependency/{$callee}/trace.log");
    }

    protected static function logDebug($info, $callee)
    {
        $callee = str_replace('::', '/', $callee);
        waLog::dump($info, "mailer/dependency/{$callee}/debug.log");
    }

}
