<?php

/**
 * Storage for sender settings.
 */
class mailerSenderModel extends waModel
{
    protected $table = 'mailer_sender';

    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT s.*, sp.value AS no_return_path
                FROM {$this->table} AS s
                    LEFT JOIN mailer_sender_params AS sp
                        ON s.id=sp.sender_id
                            AND sp.name='no_return_path'
                            AND sp.value<>''
                ORDER BY s.name";
        return $this->query($sql)->fetchAll($key, $normalize);
    }

    public function deleteById($id)
    {
        $spm = new mailerSenderParamsModel();
        $spm->deleteByField('sender_id', $id);
        return parent::deleteById($id);
    }

    public function getByType($type)
    {
        $sql = "SELECT s.*
                FROM {$this->table} AS s
                    JOIN mailer_sender_params AS sp
                        ON s.id=sp.sender_id
                            AND sp.name='type'
                            AND sp.value=?";
        return $this->query($sql, [$type])->fetchAll('id');
    }

    public function getSendersWithUnknownType()
    {
        $sql = "SELECT s.*
                FROM {$this->table} AS s
                    JOIN mailer_sender_params AS sp
                        ON s.id=sp.sender_id
                            AND sp.name='type'
                            AND sp.value NOT IN (?)";
        return $this->query($sql, [[
            'mail',
            'sendmail',
            'smtp',
            'test',
            'default',
            'wa',
        ]])->fetchAll('id');
    }
}
