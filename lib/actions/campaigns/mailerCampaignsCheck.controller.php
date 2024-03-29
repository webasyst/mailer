<?php
/**
 * Check return-paths for bounces.
 * Called in backgroung by browser JS while user is in backend.
 */
class mailerCampaignsCheckController extends waJsonController
{
    public function execute()
    {
        if (wa('mailer')->getConfig()->getOption('disable_web_worker')) {
            return;
        }
        wao(new mailerChecker(array(
            'limit' => wa('mailer')->getConfig()->getOption('returnpath_check_limit_web'),
        )))->check();
    }
}

