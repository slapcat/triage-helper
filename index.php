<?php
$page = $_SERVER['PHP_SELF'];
$sec = "300"; // refresh every 5 mins
?>

<html>
<head>
<meta http-equiv="refresh" content="<?php echo $sec?>;URL='<?php echo $page?>'">
<link rel="stylesheet" type="text/css" href="style.css">
<title>Triage Helper</title>
</head>
<body>
<h2>Please assign tickets to...</h2>

        <!-- select id="sel" onchange="toggle()" style="top:10px;right:10px;position:absolute;z-index=2">
            <option value="1" selected>Table</option>
            <option value="2">Pop-Up</option>
        </select -->

<div id="agent-table" style="display:block">
<table id="agents" class="agent-table">
    <colgroup>
       <col span="1" style="width: 25%;">
       <col span="1" style="width: 75%;">
    </colgroup>
<tbody>
  <tr>
    <th>Agent</th>
    <th>Expertise</th>
  </tr>

<?php

// OPEN CSV
$row = 1;
$agents = "";
$out = "";

if (($handle = fopen("https://box.nabasny.com/index.php/s/3swmBMxZYEZaB2f/download/IMCCS_hours.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// DEFINE TIMES
	$weekday = date( 'N' );  // Mon (1) - Sun (7)
	$hour = date( 'G' ); // 24hr digit
	if (date ( 'i' ) > 29) {
	$hour = $hour + .5;
	}
	if ($weekday > 5) {
	$timein = 9; // Use weekend time
	} else {
	$timein = 8; // Use weekday time
	}
	$buffertime = ((int)$data[$timein] - (int)2);
	$timeout = ((int)$data[$timein] + (int)8.5);

	// CHECK IF WORKING NOW
	if ($data[$weekday] == 1 && $hour >= $buffertime && $hour < $timeout) {
		if (substr($data[0], 0, 1) == '#') {
		$out = $out . " " . '<a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . substr($data[0], 1) . '</a>';
		} else {
		//$agents = $agents . '<br /><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank"><div class="tooltip">' . $data[0] . '<span class="tooltiptext">' . $data[11] . '</span></div></a>';
		$agents = $agents . '<tr><td><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . $data[0] . '</a></td><td>' . $data[11] . '</td></tr>';

		}
	}
    }
    fclose($handle);
}

// PRINT ACTIVE AGENTS
echo $agents . '</tbody></table></div>';

if ($out != "") {
	echo "<br /><div class=\"out\"><font color=\"red\"><b>Currently out:<br />";
	echo $out . "</b></font>";
}
?>
</div>

<br /><br />
<a href="https://box.nabasny.com/index.php/s/3swmBMxZYEZaB2f" target="_blank" style="color:#0096FF;font-size:22px;">Edit Schedule</a>

</body>
</html>
