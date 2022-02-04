<?php
session_start();

if ($_SESSION['auth'] == "") {
	header("Location: index.php");
}
?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css">
<title>Triage Helper</title>
</head>

<body>
<div id="agents">
<?php
$config = parse_ini_file("settings.ini", TRUE);

// DEFINE VARIABLES
$row = 1;

// DEFINE TIMES
$weekday = date( 'N' );  // Mon (1) - Sun (7)
$date = date('F j, Y');
$file = 'lineups/' . date('M-j-Y') . '.csv';

// CHECK IF DATE SELECTED
if (isset($_POST['day'])) {
	$weekday = date('w', strtotime($_POST['day'])); // SUN = 0
	if ($weekday == 0) { $weekday = 7; }

	$file = 'duties.csv';

	$date = DateTime::createFromFormat('Y-m-d', $_POST['day']);
	$date = $date->format('F j, Y');
}

// DEFINE ARRAYS
foreach ($config["jobs"] as $num => $job) {
	if (isset($job)) { $$job = array(); }
}


// LOAD THE CURRENT LINE UP
if (($handle = fopen($file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	if (is_numeric($data[1])) {
	$start = gmdate('g:i a', floor($data[1] * 3600));
	$end = gmdate('g:i a', floor(($data[1] + $config["duration"][$data[0]]) * 3600));

	$names = $data[$weekday + 1];
	$names = rtrim($names, " "); // remove trailing comma
	$names = rtrim($names, ","); // remove trailing comma
	$names = str_replace(",", ", ", $names);
	$names = rtrim($names, ","); // remove trailing comma

	$job = $config["jobs"][$data[0]];
	$$job["$start to $end"] = $names;
	}

   }
   fclose($handle);
}

$range = "";
$agents = "";
?>
<h1>Duties for <?php echo $date; ?></h1>
<form action="daily-schedule.php" method="post">
  <input type="date" id="day" name="day">
  <input type="submit" value="change date">
</form>
<center>
<?php
foreach ($config["jobs"] as $num => $job) {
	echo "<table><caption>$job</caption><tr><th></th>";

	foreach ($$job as $time => $names) {
		$range .= "<th>$time</th>";
		$agents .= "<td>$names</td>";
	}
	echo "$range</tr><tr><th>Agents</th>$agents</tr></table><br />";

	$range = "";
	$agents = "";
}
?>
</center>

<br />
<a href="index.php" style="font-size:16px;">☚ back to live view</a>

</div>
</body>
</html>
