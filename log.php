<tt><p style="padding-left: 100px;padding-top: 25px;">
<?php
$log = file_get_contents('triage.log');
echo nl2br($log);
?>
