<?php

/**
 * 23/09/2022 18:35:00
 */

$model = new waModel();

try {
    $model->exec('SELECT count_products FROM mailer_message LIMIT 0');
} catch (waDbException $e) {
    $model->exec('ALTER TABLE mailer_message ADD count_products INT(11) DEFAULT 0 AFTER rebody');
}


