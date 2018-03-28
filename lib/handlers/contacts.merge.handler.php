<?php

/**
 * Update all tables when several contacts are merged into one.
 */
class mailerContactsMergeHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $master_id = $params['id'];
        $merge_ids = (array)$params['contacts'];

        $m = new waModel();

        $mm = new mailerMessageModel();
        $lm = new mailerMessageLogModel();
        $sbm = new mailerSubscriberModel();
        $slm = new mailerSubscribeListModel();
        $drm = new mailerDraftRecipientsModel();
        $fm = new mailerFormModel();

        //
        // All the simple cases: update contact_id in tables
        //
        foreach (array(
                     array($mm->getTableName(), 'create_contact_id'),
                     array($lm->getTableName(), 'contact_id'),
                     array($sbm->getTableName(), 'contact_id'),
                     array($slm->getTableName(), 'create_contact_id'),
                     array($drm->getTableName(), 'contact_id'),
                     array($fm->getTableName(), 'create_contact_id'),

                 ) as $pair) {
            list($table, $field) = $pair;
            $sql = "UPDATE $table SET $field = :master WHERE $field IN(:ids)";
            $m->exec($sql, array('master' => $master_id, 'ids' => $merge_ids));
        }
        return null;
    }
}
