<html>
<head>
<link rel="stylesheet" type="text/css" href="style_ed.css">
</head>
<body>
<?php
session_start();

if ($_SESSION['auth'] == "") {
	header("Location: index.php");
	exit();
}

$file = $_GET['f'];
$command = $_GET['c'];

if (isset($_POST['text']))
{
    if (file_get_contents($file . '.lock') == $_SESSION['auth']) {
    file_put_contents($file, $_POST['text']);
    unlink($file . '.lock');
    header('Location: index.php');
    exit();
    } else {
    echo '<br />Someone has stolen your session. Please try again to make your edits.';
    exit();
    }
}

if ($file == 'schedule.csv' || $file == 'duties.csv') {
	$file = $_GET['f'];
} else {
	echo 'You cannot edit that file.';
	exit();
}

if ($command == "force") {
	unlink($file . '.lock');
}

if (!file_exists($file . '.lock')) {
	$text = file_get_contents($file);
	file_put_contents($file . '.lock', $_SESSION['auth']);
} else {
	echo "<br />This file is being edited by another user. <a href=\"?f=$file&c=force\">Click here</a> to edit anyway.<br /><br />WARNING: Editing at the same time as another user may lead to data loss.";
	exit();
}

?>
<form action="" method="post">
<textarea name="text"><?php echo $text; ?></textarea>
<br />
<input type="submit" value="Save" />
<input type="reset" value="Reset" /></br />
</form>
</body>
</html>
