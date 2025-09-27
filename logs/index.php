<?php
// Prevent direct access to logs directory
header('HTTP/1.0 403 Forbidden');
exit('Access denied');
?>