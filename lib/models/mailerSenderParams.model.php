<?php

/**
 * Sender parameters, key => value storage.
 */
class mailerSenderParamsModel extends waModel
{
    protected $table = 'mailer_sender_params';

    public function getBySender($id)
    {
        return $this->select('name,value')->where('sender_id = ?', $id)->fetchAll('name', true);
    }

    public function loadForSenders(&$senders)
    {
        if (!$senders) {
            return;
        }
        $rows = $this->query("SELECT * from {$this->table} WHERE sender_id IN (?)", [array_keys($senders)]);
        foreach($rows as $row) {
            $senders[$row['sender_id']]['params'][$row['name']] = $row['value'];
        }
    }

    public function save($id, $params)
    {
        // check sender id
        $id = (int)$id;
        if (!$id) {
            return false;
        }
        // delete old params
        $this->exec('DELETE FROM '.$this->table." WHERE sender_id = i:id", array('id' => $id));

        // save
        $data = array();
        foreach ($params as $k => $v) {
            if ($v) {
                $data[] = "(".$id.', "'.$this->escape($k).'", "'.$this->escape($v).'")';
            }
        }
        if ($data) {
            $sql = "INSERT INTO ".$this->table." (sender_id, name, value) VALUES ".implode(', ', $data);
            return $this->exec($sql);
        }
        return true;
    }
}