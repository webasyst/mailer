<?php

/**
 * Implements core recipient selection criteria.
 * See recipients.prepare event description for details.
 */
class mailerMailerRecipientsPrepareHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $message_id = (int) $params['id'];
        $recipients = &$params['recipients'];
        $mlm = new mailerMessageLogModel();
        $msm = new mailerSubscriberModel();
        $msl = new mailerSubscribeListModel();
        $cem = new waContactEmailsModel();

        $total_non_unique = 0;
        $insert_rows = array();
        $insert_sql = "INSERT IGNORE INTO mailer_draft_recipients (message_id, contact_id, name, email) VALUES ";
        foreach($recipients as $r_id => &$r) {
            $value = $r['value'];

            // Being paranoid...
            if (!strlen($value)) {
                continue;
            }

            // Skip list types supported by plugins
            if ($value[0] == '@') {
                continue;
            }

            // Is it subscribers list id?
            if (wa_is_int($value)) {
                $where_sql = "";
                if ($value > 0) {
                    $list = $msl->getListById($value);

                    if (!$list) {
                        continue;
                    }
                }
                $r['name'] = $value ? $list['name'] : _w('All subscribers');
                $r['count'] = $msm->countListView("", $value);
                $r['group'] = _w('Subscribers');
                if ($params['action'] === 'UpdateDraftRecipientsTable') {
                    if ($value > 0) {
                        $where_sql = " WHERE s.list_id = ".(int)$value;
                    }

                    $sql = "INSERT IGNORE INTO mailer_draft_recipients (message_id, contact_id, name, email)
                                    SELECT ".$message_id.", IFNULL(c.id, 0), IFNULL(c.name, ''), e.email
                                    FROM mailer_subscriber AS s
                                    LEFT JOIN wa_contact AS c ON c.id = s.contact_id
                                    LEFT JOIN wa_contact_emails AS e ON e.id = s.contact_email_id
                                    {$where_sql}";
                    $mlm->exec($sql);
                }
                $total_non_unique += $r['count'];
                continue;
            }

            // Is it a ContactsCollection hash?
            if ($value[0] == '/') {

                $this->prepareRecipient($r);

                if ($r) {

                    if ($params['action'] === 'UpdateDraftRecipientsTable') {
                        $this->updateDraftRecipients($r, $message_id);
                    }

                    $total_non_unique += $r['count'];
                } else {
                    unset($recipients[$r_id]);
                }

                continue;
            }

            // Otherwise, it is a list of emails.
            $mail_addresses = wao(new mailerMailAddressParser($value))->parse();
            $r['count'] = count($mail_addresses);
            $r['group'] = null;
            $r['name'] = _w('Additional emails');
            if ($params['action'] === 'UpdateDraftRecipientsTable') {
                foreach ($mail_addresses as $address) {
                    $contact_id = (int)$cem->getContactIdByEmail($address['email']);
                    $insert_rows[] = sprintf("(%d,%d,'%s','%s')", $message_id, $contact_id, $mlm->escape($address['name']), $mlm->escape($address['email']));
                    if (count($insert_rows) > 50) {
                        $mlm->exec($insert_sql . implode(',', $insert_rows));
                        $insert_rows = array();
                    }
                }
            }
            $total_non_unique += $r['count'];
        }

        if ($params['action'] === 'UpdateDraftRecipientsTable') {
            if ($insert_rows) {
                $mlm->exec($insert_sql . implode(",", $insert_rows));
            }
            unset($insert_rows);
        }

        $params['recipients_count_not_unique'] = $total_non_unique;
    }

    private function prepareRecipient(&$recipient)
    {
        // Delegate to proper proxy
        $d = mailerDependency::resolve();
        $result = $d->call(__METHOD__, $recipient);
        if (!$result['call']) {
            $recipient = null;
        }
        if ($recipient && strlen($recipient['name']) <= 0) {
            $r['name'] = $recipient['value'];
        }
    }

    private function updateDraftRecipients($recipient, $message_id)
    {
        // Delegate to proper proxy
        $d = mailerDependency::resolve();
        $params = array(
            'recipient' => $recipient,
            'message_id' => $message_id
        );
        $d->call(__METHOD__, $params);
    }

}

