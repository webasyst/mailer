<?php

/**
 * 18 2022 08:30:00
 *
 * Обновление для добавления новых 9 шаблонов рассылки
 */

try {
    $templates_promo_dir = $this->getAppPath('templates/preinstalled');
    if (
        $templates_promo_dir
        && is_readable($templates_promo_dir)
        && is_dir($templates_promo_dir)
        && class_exists('ZipArchive')
    ) {
        $locale = wa()->getLocale();
        $new_templates = [
            'Ecom_Holliday',
            'Ecom_Sweet',
            'Pets',
            'Black_Friday',
            'Interior',
            'Real_Estate',
            'Journeys',
            'Street_Food',
            'Workshops'
        ];
        $model = new mailerMessageParamsModel();

        foreach (waFiles::listdir($templates_promo_dir) as $tmpl_name) {
            if (in_array($tmpl_name, $new_templates, true)) {
                $tmpl_dir = $templates_promo_dir.'/'.$tmpl_name;
                if (
                    is_readable($tmpl_dir)
                    && is_dir($tmpl_dir)
                    && strpos($tmpl_name, '.') !== 0
                ) {
                    $file = null;
                    $files = array_fill_keys(waFiles::listdir($tmpl_dir), 1);
                    $f = "$tmpl_name.$locale.zip";
                    if (!empty($files[$f])) {
                        $file = $f;
                    }
                    if ($file) {
                        $file = $tmpl_dir.'/'.$file;
                        $install_templates = $model
                            ->select('*')
                            ->where('name = "install_template" AND value = ?', $tmpl_name)
                            ->fetchAll('value');
                        if (!empty($install_templates[$tmpl_name])) {
                            continue;
                        }
                        if (is_readable($file)) {
                            $message_id = mailerTemplatesImport2Action::importFirst($file);

                            /** оставляем параметр с информацией об установленном шаблоне */
                            $model->insert([
                                'name'       => 'install_template',
                                'value'      => $tmpl_name,
                                'message_id' => $message_id
                            ]);
                        }
                    }
                }
            }
        }
    }
} catch (waException $e) {

}
