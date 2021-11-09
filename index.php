<?php
$config = parse_ini_file("settings.ini", TRUE);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

ini_set('session.gc_maxlifetime', 43200);
session_set_cookie_params(43200);
session_start();

if (isset($_SESSION['auth']) && $_SESSION['auth'] != "") {
	goto skip;
}

if (isset($_POST["pass"])) { $pass = $_POST["pass"]; }

if (empty($pass)) {

	printf('<body style="background-color:lightgrey"><center><br /><br /><form name="input" action="index.php" method="POST"><label for="pass">Enter Password</label><br /><input name="pass" id="pass" type="password" class="formbox" placeholder="password" /><br /><br /><input name="submit" id="submit" type="submit" value="Login" /></form></center></body>');
	die();

} elseif ($pass == $config["app"]["pass"]) {
	$_SESSION['auth'] = session_id();
} elseif ($pass == $config["app"]["admin_pass"]) {
	$_SESSION['auth'] = 'admin:' . session_id();
} else {

	echo '<body style="background-color:red"><center><h1 style="color:white;margin-top:20px;font-family:Helvetica;">WRONG PASSWORD</h1><br /><br /><tt><a href="index.php">[ click here to go back ]</a></tt></center></body>';
	die();

}

skip:
?>
<html>
<head>
<meta http-equiv="refresh" content="<?php echo $config['app']['auto-refresh'] ?>;URL='<?php echo $_SERVER['PHP_SELF']; ?>'">
<link rel="stylesheet" type="text/css" href="style.css">
<link href="https://unpkg.com/tabulator-tables@4.9.3/dist/css/tabulator_site.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@4.9.3/dist/js/tabulator.js"></script>
<title>Triage Helper</title>
</head>
<?php

// DEFINE VARIABLES
$row = 1;
$agents = array();
$out = array();
$off = array();
$active = "";
$columns = "";

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


// LOAD THE CURRENT LINE UP
$file = './lineups/' . date('M-j-Y') . '.csv';
if (!file_exists($file)) {

$contents = file_get_contents($config['db']['duties']);
$pattern = "/^$weekday.*\$/m";

if(preg_match_all($pattern, $contents, $matches)){
        $duties = implode("\n", $matches[0]);
} else {
        // not a weekday
}

file_put_contents($file, $duties);

}

if (($handle = fopen($file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	$num = count($data);
	$row++;


	foreach ($config["jobs"] as $num => $job) {
	if (!isset($$job)) { $$job = ""; }

	if ($data[0] == $weekday && $hour >= $data[1] && $data[2] == $num) {

		$endshift = $data[1] + $config['duration'][$num];
		if ($hour < $endshift) {
			$$job .= $data[3];
		}
	}
	}
    }

	   fclose($handle);
}

// CONVERT DUTIES TO ARRAY AND BUILD COLUMNS FOR TABLE
foreach ($config["jobs"] as $num => $job) {
	$$job = explode(",", $$job);
	$columns .= '{title:"'.ucwords($job).'", field:"'.$job.'", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},';
}

// CHECK THE SCHEDULE
$row=1;
$offNow = TRUE;

if (($handle = fopen($config["db"]["schedule"], "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// DEFINE IN & OUT TIMES
	$buffertime = (floatval($data[$timein]) - $config["app"]["preshift_buffer"]);
	$timeout = (floatval($data[$timein]) + $config["app"]["shift_length"]);
	$tomorrow = (int)$weekday + 1;
	$timein_tmrw = floatval($timein);
	if ($tomorrow == 8) {
		$tomorrow = 1;
		$timein_tmrw = 8;
	}
	$tmrwdate = date("n/j",strtotime("+1 days")) . ",";

	// CHECK IF WORKING NOW
	if ($data[$weekday] == 1 && $hour >= $buffertime && $hour < $timeout) {
		if (substr($data[0], 0, 1) !== '#') {
			$agents[$data[0]] = array($data[11], $data[10]);

			$offNow = FALSE;
			foreach ($config["jobs"] as $job) {
				if (in_array($data[0], $$job)) {
					$agents[$data[0]][] = 1;
				} else {
					$agents[$data[0]][] = 0;
				}
			}

		}
	}

	// CHECK IF WORKING TOMORROW MORNING
	if ($data[$tomorrow] == 1 && $hour >= 18 && ($data[$timein_tmrw] == 6 || $data[$timein_tmrw] == 8)) {

		// CHECK IF SCHEDULED OUT FOR TOMORROW
		if (strpos($dates[12], $tmrwdate) !== false) {
			// AGENT IS OFF TOMORROW
		} else {
			// REMOVE # IF AGENT WAS OUT TODAY
			if (substr($data[0], 0, 1) == '#') {
				$agents[substr($data[0], 1) . ' [am]'] = array($data[11], $data[10]);
			} else {
				$agents[$data[0] . ' [am]'] = array($data[11], $data[10]);
			}
		}
	}

	// CHECK IF OUT FOR THE DAY
	if (substr($data[0], 0, 1) == '#') {
		$out[substr($data[0], 1)] = $data[10];
		$offNow = FALSE;
	}

	// NONE OF THE ABOVE - AGENT IS OFF SHIFT - exclude first row
	if ($offNow && $data[0] != "name") {
		$off[] = $data[0];
	}

	// REMOVE AGENTS FROM LIST ACCORDING TO BUFFER
	if ($hour > ($timeout - $config["app"]["postshift_buffer"])) {
		unset($agents[$data[0]]);
	}

	$offNow = TRUE; // reset

    }
    fclose($handle);
}

// REPLACE AGENTS WHO ARE OUT FOR DUTIES
if (!empty($out)) {

	$replace = array();

	foreach ($config["jobs"] as $num => $job) {
		$replace[$job] = array_intersect($$job, array_keys($out));
		$replace[$job] = array_intersect($$job, $off);
	}

	// LOGGING
	$log = array();
	$logfile = 'triage.log';
	$log["Out"] = $replace;
	$log["AvailableAgents"] = array_keys($agents);

	$allAgents = $agents;
	shuffle_assoc($agents);
	shuffle_assoc($allAgents);

	foreach ($replace as $task => $names) {

		foreach ($names as $name) {

			// remove out person
			$key = array_search($name, $$task);
			unset($$task[$key]);
		    	array_values($$task);

			// add random person
			foreach ($agents as $agent => $exp) {

				// check for a ringer (not on any other duties)
				if (in_array($agent, $phones) == FALSE && in_array($agent, $triage) == FALSE && in_array($agent, $chat) == FALSE && in_array($agent, $sweeper) == FALSE) {
					$ringer = $agent;
				}

				// remove if on the duty already
				if (in_array($agent, $$task) == TRUE) {
					unset($agents[$agent]);
				}

				// don't consider if on two other jobs already
				$i=0;
				foreach($replace as $duty => $replacableNames) {
				if (in_array($agent, $$duty) == TRUE) {
					$i++;
				}
				}

				if ($i >= 2) {
					unset($agents[$agent]);
				}

				// if on chat or phones already, don't let both be assigned
				if ($task == "chat" || $task == "phones") {
				if (in_array($agent, $phones) == TRUE && $task == "chat") {
				    unset($agents[$agent]);
				} elseif (in_array($agent, $chat) == TRUE && $task == "phones") {
				    unset($agents[$key]);
				}
				}
			}

			$log[$task][$name]["Trimmed"] = array_keys($agents);

			// grab an agent from the remaining
			if ($ringer != "") {
				$$task[] = $ringer;
				unset($agents[$ringer]);
				replaceAgent($name, $ringer, $task, $file);
				$log[$task][$name]["Ringer"] = $ringer;
				$ringer = "";
			} elseif ($agents != NULL) {
				$replacement = array_key_first($agents);
				$$task[] = $replacement;
				unset($agents[$replacement]);
				replaceAgent($name, $replacement, $task, $file);
				$log[$task][$name]["ReplacementAgent"] = $replacement;
			} else {
				$replacement = array_key_first($allAgents);
				$$task[] = $replacement;
				unset($agents[$replacement]);
				replaceAgent($name, $replacement, $task, $file);
				$log[$task][$name]["ReplacementAgent"] = $replacement;
			}
			$oldLog = file_get_contents($logfile);
			file_put_contents($logfile, "\n[-------- " . $name . " ----- " . $task . " ------- " . date(DATE_RFC2822) . " ------]\n" . var_export($log, TRUE) . $oldLog);
			$log = array();
		}
	}

$agents = $allAgents;

}

// CUSTOM FUNCTIONS
function replaceAgent($name, $rand_agent, $task, $file)
{
global $weekday;
global $config;
$duties = file_get_contents($file);
$task_num = array_search($task, $config["jobs"]);

$pattern = '/(' . $weekday . ',.*,' . $task_num . ',.*)' . $name . '(.*)/i';
$replace = '$1' . $rand_agent . '$2';
$new = preg_replace($pattern, $replace, $duties);
file_put_contents($file, $new);

$message = "<html><body style=\"font-family:Helvetica,Arial;font-size:18px;\"><h1>AGENT REPLACEMENT</h1><b>$name</b> has been replaced by <b><u>$rand_agent</u></b> for <i>$task</i>.<br /><br />Thank you for helping out the team!<br /><br />Find new shift information here: <a href=\"" . $config["app"]["url"] . "daily-schedule.php\">Daily Schedule</a></body></html>";
gmail($rand_agent, $message);
}

function shuffle_assoc(&$array) {
$keys = array_keys($array);

shuffle($keys);

foreach($keys as $key) {
    $new[$key] = $array[$key];
}

$array = $new;

return true;
}

function gmail($name, $message) {
global $config;
$mail = new PHPMailer();
$mail->IsSMTP();
$mail->Mailer = "smtp";

$mail->SMTPAuth   = TRUE;
$mail->SMTPSecure = "tls";
$mail->Port       = $config["email"]["smtp_port"];
$mail->Host       = $config["email"]["smtp_host"];
$mail->Username   = $config["email"]["smtp_user"];
$mail->Password   = $config["email"]["smtp_pass"];

$mail->IsHTML(true);
$mail->AddAddress($config["email"]["recipient"]);
$mail->SetFrom($config["email"]["smtp_user"], "Triage Helper");
$mail->AddReplyTo($config["email"]["recipient"]);
$mail->Subject = "TRIAGE ALERT - " . $name;

$mail->MsgHTML($message);
if(!$mail->Send()) {
  error_log($mail);
}
}

if (!empty($out)) {
	echo "<div class=\"out-banner\">Currently out:  ";

	foreach ($out as $name => $link) {
	echo '<a href="' . $config["tickets_URL"]["pre"] .
		$link .  $config["tickets_URL"]["post"] . '" target="_blank">' . $name . '</a>  ';
	}

	echo '</div>';
}

// LOAD ACTIVE AGENTS
foreach ($agents as $name => $info) {
    $active .= '{agent:"' . $name . '", expertise:"' . $info[0] . '", ';

    $task_count = 1;
    for ($i = 2 ; $i < count($info); $i++) {
        $active .= $config["jobs"][$task_count] . ":";
        $active .= $info[$i];
        $active .= ", ";

        $task_count++;
    }

  $active .= 'tickets:"' . $config["tickets_URL"]["pre"] . $info[1] .
			$config["tickets_URL"]["post"] . '"},';
}
?>

<body>
<div class="content">
<div class="title" style="margin-top:5px;"><font size="12px">Please assign tickets to...</font>
<div class="dropdown">
  <button onclick="myFunction()" class="dropbtn">✎</button>
  <div id="myDropdown" class="dropdown-content">
    <a href="daily-schedule.php">Daily Schedule</a>
    <a href="<?php echo 'editor.php?f=' . $config["db"]["schedule"] ?>">Edit Schedule</a>
    <a href="<?php echo 'editor.php?f=' . $config["db"]["duties"] ?>">Edit Duties</a>
    <a href="<?php echo 'editor.php?f=settings.ini' ?>">Settings</a>
    <a href="log.php" target="_blank">Replacement Log</a>
    <a href="docs.html" target="_blank">Documentation</a>
    <a class="critical" onClick="Confirm()">Reset Duties</a>
    <a href="logout.php">Logout</a>
  </div>
</div></div>

<script>
function myFunction() {
  document.getElementById("myDropdown").classList.toggle("show");
}

window.onclick = function(event) {
  if (!event.target.matches('.dropbtn')) {
    var dropdowns = document.getElementsByClassName("dropdown-content");
    var i;
    for (i = 0; i < dropdowns.length; i++) {
      var openDropdown = dropdowns[i];
      if (openDropdown.classList.contains('show')) {
        openDropdown.classList.remove('show');
      }
    }
  }
}

function Confirm() {
  var r = confirm("WARNING! This will reset all assigned duties to the defaults. Press OK if you are sure you want to proceed.");
  if (r == true) {
    window.location.replace("reset_duties.php");
  }
}
</script>

<div id="full-table"></div>

<script type="text/javascript">
var tabledata = [
<?php echo $active; ?>
];
var table = new Tabulator("#full-table", {
    data:tabledata,
    layout:"fitColumns",
    movableColumns:true,
columns:[
        {title:"Agent", field:"agent", width:100, formatter:"link", formatterParams:{urlField:"tickets",target:"_blank",}},
        {title:"Expertise", field:"expertise", headerFilter:"input"},
<?php echo $columns; ?>
    ],
});
</script>
<br />
<p style="font-size:small;">[am] = Agent will be available in the morning.</p>
</div>
</body>
</html>
