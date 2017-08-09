<?php

class mailerPersonalSettingsSaveController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('webasyst')) {
            throw new waException('Access denied.', 403);
        }

        $theme = new waTheme(waRequest::get('theme_id'), 'mailer');
        if ($theme['type'] == waTheme::ORIGINAL) {
            $theme->copy();
        }

        $files = waRequest::post('files', array(), 'array_trim');
        $files = array_intersect_key($files, array('my.subscriptions.html' => 1));

        foreach ($files as $file => $content) {
            $file_path = $theme->getPath().'/'.$file;
            if (!file_exists($file_path) || is_writable($file_path)) {
                if ($content || file_exists($file_path)) {
                    $r = @file_put_contents($file_path, $content);
                    if ($r !== false) {
                        $r = true;
                    }
                } else {
                    $r = @touch($file_path);
                }
            }
        }
    }
}