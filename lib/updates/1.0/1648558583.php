<?php

$files_to_delete = [
    'lib\classes\mailerPhpMailTransport.class.php',
    'lib\classes\mailerTestTransport.class.php',
    'lib\updates\1.0.23\1610623618.php',
    'lib\updates\1349731415.php',
    'lib\updates\1349931415.php',
    'lib\updates\1350331415.php',
    'lib\updates\1350531415.php',
    'lib\updates\1350893141.php',
    'lib\updates\1351131415.php',
    'lib\updates\1351231415.php',
    'lib\updates\1351583141.php',
    'lib\updates\1352831415.php',
    'lib\updates\1353631415.php',
    'lib\updates\1356571828.php',
    'lib\updates\1363831415.php',
    'lib\updates\1363931415.php',
    'lib\updates\1364531415.php',
    'lib\updates\1366642583.php',
    'lib\updates\1396870812.php',
    'lib\updates\1397130860.php',
    'lib\updates\1397130863.php',
    'lib\updates\1398064496.php',
    'lib\updates\1425028708.php',
    'lib\updates\1617799735.php',
    'lib\updates\1639394197.php',
];

foreach($files_to_delete as $file) {
    waFiles::delete($this->getAppPath($file), true);
}
