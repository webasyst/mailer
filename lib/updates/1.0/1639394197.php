<?php

$m = new waModel();
$table = 'mailer_message';
$field = 'rebody';

try {
    $m->query("SELECT `$field` FROM `$table` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `$table` ADD `$field` MEDIUMTEXT NOT NULL");
}

