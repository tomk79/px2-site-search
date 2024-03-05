<?php
echo '---------------- test'."\n";

$paprika->log()->trace('Trace Test.');
$paprika->log()->debug('Debug Test.');
$paprika->log()->info('Info Test.');
$paprika->log()->warn('Warning Test.');
$paprika->log()->error('Error Test.');
$paprika->log()->fatal('Fatal Test.');

exit;
