<?php

$files_to_delete = [
    'lib/classes/transport/wa/mailerWaTransportApiUrlConfig.class.php',
    'lib/classes/transport/wa/mailerWaTransportEndpointsConfig.class.php',
    'lib/classes/transport/wa/mailerWaTransportServiceUrlProvider.class.php',
];

foreach($files_to_delete as $file) {
    waFiles::delete($this->getAppPath($file), true);
}