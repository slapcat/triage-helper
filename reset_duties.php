<?php
$config = parse_ini_file("settings.ini", TRUE);

session_start();

if ($_SESSION['auth'] != TRUE) {
	echo 'You must login first!';
}

$file = './lineups/' . date('M-j-Y') . '.csv';
if (file_exists($file)) {
	unlink($file);
}
header("Location: index.php");
?>
