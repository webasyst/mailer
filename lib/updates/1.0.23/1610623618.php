<?php

$_migrates = new mailerMigrates();
$_migrates->addColumn('mailer_return_path', 'no_plus', 'TINYINT(1) NOT NULL DEFAULT 0');
