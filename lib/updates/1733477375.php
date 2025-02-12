<?php

$model = new waModel();

try {
    $model->exec('SELECT `no_plus` FROM `mailer_return_path` LIMIT 0');
} catch (waDbException $e) {
    $model->exec('ALTER TABLE `mailer_return_path` ADD COLUMN `no_plus` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ssl`');
}
