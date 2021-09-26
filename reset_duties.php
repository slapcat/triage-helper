<?php
$file = './lineups/' . date('M-j-Y') . '.csv';
if (file_exists($file)) {
	unlink($file);
}
header("Location: https://nabasny.com/triage/");
?>
