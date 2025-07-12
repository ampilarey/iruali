<?php
echo "Test file is working!";
echo "<br>Current time: " . date('Y-m-d H:i:s');
echo "<br>PHP version: " . PHP_VERSION;
echo "<br>Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set');
echo "<br>Script path: " . __FILE__;
?> 