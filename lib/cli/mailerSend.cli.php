<?php

/**
 * /path/to/php /path/to/wa/cli.php mailer send
 *
 * This controller should be called by CRON to continue sending of big campaigns.
 */
class mailerSendCli extends waCliController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $asm->set('mailer', 'last_cron_time', time());

        if ( ( $id = waRequest::param(0)) && wa_is_int($id)) {
            $mailer_message = new mailerMessage($id);
            $mailer_message->send();
            return;
        }

        $message_model = new mailerMessageModel();
        $messages = $message_model->getMessageForSend(true);
        $fire_event = false;

        $cli_user = wa()->getUser();

        foreach ($messages as $message) {

            if ($message['send_datetime'] > date('Y-m-d H:i:s')) {

                $fire_event = true;

            } else {

                $mailer_message = new mailerMessage($message);

                // Campaign params
                $mpm = new mailerMessageParamsModel();
                $params = $mpm->getByMessage($message['id']);

                // Before call mailerHelper preparation (and update) methods set current user to author of campaign
                // Because campaign can has recipients (for example crm segments) access to which has only author

                $author_id = $message['create_contact_id'];
                $author_user = new mailerCliAuthUser($author_id);

                wa()->setUser($author_user);

                if ($message['status'] != mailerMessageModel::STATUS_SENDING) {
                    // renew recipients for pending campaigns
                    mailerHelper::updateDraftRecipients($message['id'], 'UpdateDraftRecipientsTable');
                    mailerHelper::prepareRecipients($message, $params);
                    $mailer_message->status(mailerMessageModel::STATUS_SENDING);
                }

                wa()->setUser($cli_user);

                $mailer_message->send();
            }
        }
        if ($fire_event) {
            wa()->event('campaign.before_sending');
        }
    }
}

