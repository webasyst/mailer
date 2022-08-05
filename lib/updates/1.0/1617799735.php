<?php

$message_params_model = new mailerMessageParamsModel();

$params = $message_params_model->getByField('name', 'sender_params', 'message_id');
foreach ($params as $param) {
    $sender_params = unserialize($param['value']);
    foreach (['reply_to', 'type', 'server', 'port'] as $sender_param) {
        if (isset($sender_params[$sender_param])) {
            $new_data = [
                'message_id' => $param['message_id'],
                'name' => 'sender_' . $sender_param,
                'value' => $sender_params[$sender_param]
            ];
            try {
                $message_params_model->insert($new_data);
            } catch (waException $e) {}
        }
    }
}
