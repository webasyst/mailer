<?php

class mailerFilesDeleteController extends waJsonController {

    public function execute() {

        $message_id = waRequest::request('message_id');
        $file_name = waRequest::request('file_name');

        try {
            if ($file_name) {
                if ($message_id) {
                    $path = realpath(wa()->getDataPath('files/' . $message_id . '/' . $file_name, true, 'mailer', false));
                } else {
                    $path = realpath(wa()->getDataPath('files/' . $file_name, true, 'mailer', false));
                }

                $dir_path = realpath(wa()->getDataPath('files/', true, 'mailer', false));

                if (strncmp($path, $dir_path, strlen($dir_path)) === 0) {
                    waFiles::delete($path);
                }
            }
        } catch (Exception $e) {

        }
    }
}