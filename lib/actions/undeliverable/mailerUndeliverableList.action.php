<?php

/**
 * List of emails with delivering errors in the past.
 */
class mailerUndeliverableListAction extends waViewAction
{
    public function execute()
    {
        if (!mailerHelper::isAdmin()) {
            throw new waException('Access denied.', 403);
        }

        // Remove one email from this list, if specified
        $mark_email = (string) waRequest::request('mark_email');
        if (strlen($mark_email) > 0) {
            $cem = new waContactEmailsModel();
            $cem->updateByField(array(
                'email' => $mark_email,
                'status' => 'unavailable',
            ), array(
                'status' => 'unknown',
            ));
        }

        // Parameters for the list
        $search = waRequest::request('search');
        $start  = waRequest::request('start', 0, 'int');
        $limit  = waRequest::request('records', 30, 'int');;
        $order  = waRequest::request('order');
        if (!in_array($order, array('name', 'email', 'datetime', '!name', '!email', '!datetime'))) {
            $order = 'email';
        }

        // Fetch data
        $total_rows = true;
        $list = self::getListView($search, $start, $limit, $order, $total_rows);

        // Format time
        foreach($list as &$row) {
            $row['datetime_formatted'] = mailerCampaignsArchiveAction::formatListDate($row['datetime']);
        }
        unset($row);

        // Prepare pagination for template
        mailerHelper::assignPagination($this->view, $start, $limit, $total_rows);

        $this->view->assign('list', $list);
        $this->view->assign('order', $order);
        $this->view->assign('records', $limit);
        $this->view->assign('start', $start);
        $this->view->assign('search_url_append', $search ? $search.'/' : '');
        $this->view->assign('search', $search);
        $this->view->assign('columns', array(
            'name' => _w('Name'),
            'email' => _w('Email'),
            'datetime' => _w('Campaign date'),
            'subject' => _w('Campaign subject'),
        ));
    }

    public static function getListView($search='', $start=0, $limit=50, $order=null, &$total_rows=null)
    {
        $m = new mailerMessageModel();

        // Search condition
        $where_sql = '';
        if ($search) {
            $where_sql = "AND CONCAT(c.name, ' ', ce.email) LIKE '%".$m->escape($search, 'like')."%'";
        }

        // Limit
        $limit_sql = '';
        if ($limit) {
            $limit = (int) $limit;
            if ($start) {
                $limit_sql = "LIMIT {$start}, {$limit}";
            } else {
                $limit_sql = "LIMIT {$limit}";
            }
        }

        // Order
        $possible_order = array(
            'name' => 'c.name',
            'email' => 'ce.email',
            'datetime' => 'datetime',
            '!name' => 'c.name DESC',
            '!email' => 'ce.email DESC',
            '!datetime' => 'datetime DESC',
        );
        if (!$order || empty($possible_order[$order])) {
            $order = key($possible_order);
        }
        $order_sql = "ORDER BY ".$possible_order[$order];

        // Count total number of rows
        $total_rows_sql = '';
        if ($total_rows) {
            $total_rows_sql = ' SQL_CALC_FOUND_ROWS';
        }

        $sql = "SELECT{$total_rows_sql} c.name, ce.email, MAX(ml.datetime) AS datetime, GROUP_CONCAT(ml.message_id SEPARATOR ',') AS message_ids
                FROM wa_contact_emails AS ce
                    JOIN wa_contact AS c
                        ON ce.contact_id=c.id
                    LEFT JOIN mailer_message_log AS ml
                        ON ml.contact_id=ce.contact_id
                            AND ml.email=ce.email
                            AND ml.status IN (-2, -1)
                WHERE ce.status='unavailable'
                    {$where_sql}
                GROUP BY ce.id
                {$order_sql}
                {$limit_sql}";

        $result = $m->query($sql)->fetchAll();
        if ($total_rows) {
            $total_rows = $m->query('SELECT FOUND_ROWS()')->fetchField();
        }

        $message_ids = [];
        foreach($result as &$row) {
            $row['messages'] = [];
            if ($row['message_ids']) {
                $row['message_ids'] = explode(',', $row['message_ids']);
                foreach($row['message_ids'] as $id) {
                    $message_ids[$id] = $id;
                }
            }
        }
        unset($row);

        if ($message_ids) {
            $messages = $m->getById($message_ids);
            foreach($result as &$row) {
                if ($row['message_ids']) {
                    foreach($row['message_ids'] as $id) {
                        if (isset($messages[$id])) {
                            $row['messages'][$id] = array_intersect_key($messages[$id], [
                                'id' => 1,
                                'subject' => 1,
                                'status' => 1,
                                'create_datetime' => 1,
                                'send_datetime' => 1,
                                'finished_datetime' => 1,
                            ]);
                        }
                    }
                }
                unset($row['message_ids']);
            }
            unset($row);
        }

        return $result;
    }
}

