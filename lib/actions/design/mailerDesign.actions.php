<?php
/**
 * Design section.
 */
class mailerDesignActions extends waDesignActions
{
    protected $design_url = '#/design/';
    protected $themes_url = '#/design/themes/';

    public function __construct()
    {
        if (!$this->getRights('design')) {
            throw new waRightsException(_ws("Access denied"));
        }

        $this->options['is_ajax'] = true;
        $this->options['container'] = false;
    }
}
