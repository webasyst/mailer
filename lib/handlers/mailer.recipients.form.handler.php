<?php

/**
 * Implements core recipient selection criteria.
 * See recipients.form event description for details.
 */
class mailerMailerRecipientsFormHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $this->beforeMainLoop($params);
        $this->mainLoop($params);
        $this->afterMainLoop($params);
    }

    protected function beforeMainLoop(&$params)
    {
        // init group-structures
        $recipients_groups = &$params['recipients_groups'];
        $recipients_groups['subscribers'] = array(
            'name' => _w('Subscribers'),
            'content' => '',
            'opened' => false,
            'included_in_all_contacts' => true,
            'comment' => _w('This option allows selecting contacts who have opted for receiving your email newsletters using a subscription form (see the Subscribers section).'),

            // not part of event interface, but used internally here
            'all_selected_id' => false,
            'selected' => array(),
        );
        $recipients_groups['flat_list'] = array(
            'name' => _w('Additional emails'),
            'content' => null,
            'opened' => false,
            'comment' => _w('Use this field  to manually enter any additional email addresses. If such addresses are not yet contained in your contacts database, they will be added there as new contacts once the sending of this message is completed.'),

            // not part of event interface, but used internally here
            'count' => 0,
            'ids' => array(),
            'all_emails' => ''
        );
        $d = mailerDependency::resolve();
        $d->call(__METHOD__, $recipients_groups);
    }

    protected function mainLoop(&$params)
    {
        $recipients = $params['recipients'];
        $recipients_groups = &$params['recipients_groups'];

        // Loop through all message_recipients and gather data about what is selected
        foreach($recipients as $r_id => $value) {

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
                $recipients_groups['subscribers']['selected'][$r_id] = $value;
                $recipients_groups['subscribers']['opened'] = true;
                continue;
            }

            // Is it a list of emails?
            if ($value[0] != '/') {
                // Parse and count emails in this list
                // to count total number of emails
                $flat_list = array();
                $parser = new mailerMailAddressParser($value);
                foreach($parser->parse() as $a) {
                    $flat_list[mb_strtolower($a['email'])] = true;
                }

                $recipients_groups['flat_list']['ids'][] = $r_id;
                $recipients_groups['flat_list']['count'] += count($flat_list);
                $recipients_groups['flat_list']['all_emails'] .= "\n".trim($value);
                $recipients_groups['flat_list']['opened'] = true;
                unset($flat_list);
                continue;
            }

            //
            // Otherwise, it is a ContactsCollection hash.
            // Delegate to proper dependency proxy
            //

            $d = mailerDependency::resolve();
            $call_params = array(
                'recipients_groups' => $recipients_groups,
                'recipient' => array(
                    'id' => $r_id,
                    'value' => $value
                )
            );
            $d->call(__METHOD__, $call_params);
        }
    }

    protected function afterMainLoop(&$params)
    {
        //
        // Now, as we collected data about which categoies, locales, etc. are selected,
        // use it to prepare HTML parts for the form.
        //

        $recipients_groups = &$params['recipients_groups'];

        $recipients_groups['subscribers']['content'] = trim(wao(new mailerCampaignsRecipientsBlockSubscribersAction($recipients_groups['subscribers']))->display());
        $recipients_groups['flat_list']['content'] = trim(wao(new mailerCampaignsRecipientsBlockFlatListAction($recipients_groups['flat_list']))->display());
        if ($recipients_groups['flat_list']['count']) {
            $recipients_groups['flat_list']['name'] .= '<span class="hide-when-modified count"> ('.$recipients_groups['flat_list']['count'].')</span>';
        }

        $d = mailerDependency::resolve();
        $d->call(__METHOD__, $recipients_groups);
    }
}
