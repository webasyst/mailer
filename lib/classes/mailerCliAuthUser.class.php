<?php

/**
 * Class mailerCliAuthUser
 *
 * This need in CLI env when waAuthUser required
 * Sadly original waAuthUser in CLI env is always dummy (without ID)
 */
class mailerCliAuthUser extends waAuthUser
{
    public function __construct($id = null, $options = array())
    {
        parent::__construct($id, $options);
        $this->id = $id;
        $this->auth = false;
    }
}
