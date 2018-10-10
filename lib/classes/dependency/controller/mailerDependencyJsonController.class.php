<?php

class mailerDependencyJsonController extends waJsonController
{
    /**
     * @var mailerContactsProDependency
     */
    protected $d;

    public function __construct($params = null)
    {
        $is_template_value = waConfig::get('is_template');
        waConfig::set('is_template', false);
        $this->d = mailerDependency::resolve();
        waConfig::set('is_template', $is_template_value);
        if ($this->d === null) {
            $this->logResolveError();
        }
    }

    protected function logResolveError()
    {
        if (version_compare(phpversion(), '5.3.6', '<')) {
            // php version isn't high enough
            $trace = debug_backtrace(false);
        } else {
            $trace = debug_backtrace(~DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        waLog::dump($trace, 'mailer/dependency/controller/resolve_error_trace.log');
    }
}
