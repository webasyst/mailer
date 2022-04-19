<?php

// Import pre-installed templates to database
$tmpls_dir = $this->getAppPath('templates/preinstalled');
if ($tmpls_dir && is_readable($tmpls_dir) && is_dir($tmpls_dir) && class_exists('ZipArchive')) {
    $locale = wa()->getLocale();
    foreach(waFiles::listdir($tmpls_dir) as $tmpl_name) {
        // only new templates
        if(in_array($tmpl_name, ['Promo', 'Flare', 'Transactional', 'Text', 'Digest', 'Invoice'])) {
            $tmpl_dir = $tmpls_dir.'/'.$tmpl_name;
            if (is_readable($tmpl_dir) && is_dir($tmpl_dir) && strpos($tmpl_name, '.') !== 0) {
                $files = array_fill_keys(waFiles::listdir($tmpl_dir), 1);
                $file = null;
                foreach(array("{$tmpl_name}.{$locale}.zip", "{$tmpl_name}.zip") as $f) {
                    if (!empty($files[$f])) {
                        $file = $f;
                        break;
                    }
                }
                if ($file) {
                    $file = $tmpl_dir.'/'.$file;
                    if (is_readable($file)) {
                        mailerTemplatesImport2Action::importFirst($file);
                    }
                }
            }
        }
    }
}