<?php
$page = $_SERVER['PHP_SELF'];
$sec = "300"; // refresh every 5 mins
?>

<html>
<head>
<title>Triage Chart</title>
<meta http-equiv="refresh" content="<?php echo $sec?>;URL='<?php echo $page?>'">
<style>
body {
	font-family:Helvetica,Arial;
	font-size:30px;
	background-color:white;
}

.tooltip {
  position: relative;
  display: inline-block;
  border-bottom: 1px dotted dodgerblue;
}

.tooltip .tooltiptext {
  font-size: small;
  visibility: hidden;
  width: auto;
  background-color: dodgerblue;
  color: #fff;
  text-align: center;
  border-radius: 6px;
  padding: 5px 7px;
  position: absolute;
  margin-left: 15px;
  margin-top: 2px;
  z-index: 1;
  white-space: nowrap;
}

.tooltip .tooltiptext::after {
  content: " ";
  position: absolute;
  top: 50%;
  right: 100%;
  margin-top: -5px;
  border-width: 5px;
  border-style: solid;
  border-color: transparent dodgerblue transparent transparent;
}

.tooltip:hover .tooltiptext {
  visibility: visible;
}

.names {
	margin: 0 100px;
}
.names a {
	text-decoration: none;
	color: black;
}
.names a:hover {
	color: #0096FF;
}


.out a {
	text-decoration: none;
	color: red;
}
.out a:hover {
	text-decoration: underline;
}

#agents {
  font-family: Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

#agents td, #agents th {
  border: 1px solid #ddd;
  padding: 8px;
}

#agents tr:nth-child(even){background-color: #f2f2f2;}

#agents tr:hover {background-color: #ddd;}

#agents th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #04AA6D;
  color: white;
}

#agent-table { display: block; }
#names { display: none; }
</style>
</head>

<body><h2>Please assign tickets to...</h2>

        <!-- select id="sel" onchange="toggle()"  style="top:10px;right:10px;position:absolute;z-index=2">
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
if (($handle = fopen("https://box.nabasny.com/index.php/s/Pjk5xtyxsype29N/download/IMCCS_hours.csv", "r")) !== FALSE) {
  if (($handle2 = fopen("/var/www/html/nextcloud/data/jake/files/IMCCS_hours.csv", "w")) !== FALSE) {

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
	$buffertime = $data[$timein] - 2;
	$timeout = $data[$timein] + 8.5;

	// MARK IF OFF FOR TODAY
	checkTimeOff();

	// CHECK IF WORKING NOW
	if ($data[$weekday] == 1 && $hour >= $buffertime && $hour < $timeout) {
		if (substr($data[0], 0, 1) == '#') {
		$out = $out . " " . '<a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . substr($data[0], 1) . '</a>';
		} else {
		// OLD LIST FORMAT
		//$agents = $agents . '<br /><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank"><div class="tooltip">' . $data[0] . '<span class="tooltiptext">' . $data[11] . '</span></div></a>';
		$agents = $agents . '<tr><td><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . $data[0] . '</a></td><td>' . $data[11] . '</td></tr>';
		}
	}
    }
    fclose($handle2);
    fclose($handle); 
  }
}
// PRINT ACTIVE AGENTS
echo $agents . '</tbody></table></div><div class="out">';

if ($out != "") {
	echo "<br /><br /><font color=\"red\"><b>Currently out:<br />";
	echo $out . "</b></font>";
}


function checkTimeOff() {
	$dates = explode(" ", $data[12]);
	$today = date("m/d");
error_log($data[12] . $dates . $today);
	for ($i = 0; $i < count($dates); $i++) {
		if (strpos($dates[$i], $today) !== false) {
 		unset($dates[$i]);
			$data[0] = "#" . $data[0];
			fputcsv($handle2, $data);
		}
	}
}

?>
</div>

<br /><br />
<a href="https://box.nabasny.com/index.php/s/Pjk5xtyxsype29N" target="_blank" style="color:#0096FF;font-size:22px;">Edit Schedule</a>

</body>
</html>
