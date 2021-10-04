<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

ini_set('session.gc_maxlifetime', 43200);
session_set_cookie_params(43200);
session_start();

if ($_SESSION['auth'] != "") {
	goto skip;
}

$pass = $_POST["pass"];

if (empty($pass)) {

	printf('<body style="background-color:lightgrey"><center><br /><br /><form name="input" action="index.php" method="POST"><label for="pass">Enter Password</label><br /><input name="pass" id="pass" type="password" class="formbox" placeholder="password" /><br /><br /><input name="submit" id="submit" type="submit" value="Login" /></form></center></body>');
	die();

} elseif ($pass == getenv('TRIAGE_TOOL_PW')) {

	$_SESSION['auth'] = session_id();

} else {

	echo '<body style="background-color:red"><center><h1 style="color:white;margin-top:20px;font-family:Helvetica;">WRONG PASSWORD</h1><br /><br /><tt><a href="index.php">[ click here to go back ]</a></tt></center></body>';
	die();

}

skip:

$page = $_SERVER['PHP_SELF'];
$sec = "300"; // refresh every 5 mins
?>
<html>
<head>
<meta http-equiv="refresh" content="<?php echo $sec?>;URL='<?php echo $page?>'">
<link rel="stylesheet" type="text/css" href="style.css">
<link href="https://unpkg.com/tabulator-tables@4.9.3/dist/css/tabulator_site.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@4.9.3/dist/js/tabulator.js"></script>
<title>Triage Helper</title>
</head>

<body>
<div class="title" style="margin-top:38px;"><font size="12px">Please assign tickets to...</font>
<div class="dropdown">
  <button onclick="myFunction()" class="dropbtn">✎</button>
  <div id="myDropdown" class="dropdown-content">
    <a href="daily-schedule.php">Daily Schedule</a>
    <a href="<?php echo getenv('CSV_URL') ?>">Edit Schedule</a>
    <a href="<?php echo getenv('DUTIES_URL') ?>">Edit Duties</a>
    <a onClick="Confirm()">Reset Duties</a>
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
<?php

// DEFINE VARIABLES
$row = 1;
$agents = array();
$links = array();
$out = array();
$phones = "";
$triage = "";
$chat = "";
$sweeper = "";

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

$contents = file_get_contents(getenv('DUTIES_LOCAL'));
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

	//phones
	if ($data[0] == $weekday && $hour >= $data[1] && $data[2] == 1) {
		$endshift = $data[1] + 4;
		if ($hour < $endshift) {
			$phones = $phones . $data[3];
		}
	//triage
	} elseif ($data[0] == $weekday && $hour >= $data[1] && $data[2] == 2) {
		$endshift = $data[1] + 8.5;
		if ($hour < $endshift) {
			$triage = $triage . $data[3];
		}
	//chats
	} elseif ($data[0] == $weekday && $hour >= $data[1] && $data[2] == 3) {
		$endshift = $data[1] + 6;
		if ($hour < $endshift) {
			$chat = $chat . $data[3];
		}
	//sweeper
	} elseif ($data[0] == $weekday && $hour >= $data[1] && $data[2] == 4) {
		$endshift = $data[1] + 8.5;
		if ($hour < $endshift) {
			$sweeper = $sweeper . $data[3];
		}
	}
   }
   fclose($handle);
}

// CONVERT DUTIES TO ARRAYS
$phones = explode(",", $phones);
$triage = explode(",", $triage);
$chat = explode(",", $chat);
$sweeper = explode(",", $sweeper);


// CHECK THE SCHEDULE
$row=1;

if (($handle = fopen(getenv('CSV_LOCAL'), "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// DEFINE IN & OUT TIMES
	$buffertime = ($data[$timein] - 2);
	$timeout = ($data[$timein] + 7.5); // remove name an hour before end of shift
	$tomorrow = $weekday + 1;
	$timein_tmrw = $timein;
	if ($tomorrow == 8) {
		$tomorrow = 1;
		$timein_tmrw = 8;
	}
	$tmrwdate = date("n/j",strtotime("+1 days")) . ",";

	// CHECK IF WORKING NOW
	if ($data[$weekday] == 1 && $hour >= $buffertime && $hour < $timeout) {
		if (substr($data[0], 0, 1) !== '#') {
			$agents[$data[0]] = $data[11];
			$links[$data[0]] = $data[10];
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
				$agents[substr($data[0], 1) . ' [am]'] = $data[11];
				$links[substr($data[0], 1)] = $data[10];
			} else {
				$agents[$data[0] . ' [am]'] = $data[11];
				$links[$data[0]] = $data[10];
			}
		}
	}


	// CHECK IF OUT FOR THE DAY
	if (substr($data[0], 0, 1) == '#') {
		$out[] = substr($data[0], 1);
		$links[substr($data[0], 1)] = $data[10];
	}


    }
    fclose($handle);
}

// REPLACE AGENTS WHO ARE OUT FOR DUTIES
if (!empty($out)) {
	$replace = array();
	$replace["phones"] = array_intersect($phones, $out);
	$replace["triage"] = array_intersect($triage, $out);
	$replace["chat"] = array_intersect($chat, $out);
	$replace["sweeper"] = array_intersect($sweeper, $out);

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
				if (array_intersect($agent, $phones) == NULL && array_intersect($agent, $triage) == NULL && array_intersect($agent, $chat) == NULL && array_intersect($agent, $sweeper) == NULL) {
					$ringer = $agent;
					break;
				}

				// remove if on the duty already
				if (array_intersect($agent, $$task) == TRUE) {
					unset($agents[$agent]);
				}

				// don't consider if on two other jobs already
				$i=0;
				foreach($replace as $duty => $replacableNames) {
				if (array_intersect($agent, $$duty) == TRUE) {
					$i++;
				}
				}

				if ($i >= 2) {
					unset($agents[$agent]);
				}

				// if on chat or phones already, don't let both be assigned
				if ($task == "chat" || $task = "phones") {
				if (array_intersect($agent, $phones) == TRUE && $task = "chat") {
				    unset($agents[$agent]);
				} elseif (array_intersect($agent, $chat) == TRUE && $task = "phones") {
				    unset($agents[$key]);
				}
				}
			}


 			// grab an agent from the remaining
			    if ($ringer != "") {
				$$task[] = $ringer;
				unset($agents[$ringer]);
				replaceAgent($name, $ringer, $task, $file);
				} elseif ($agents != NULL) {
			    $replacement = array_key_first($agents);
        	                    $$task[] = $replacement;
					unset($agents[$replacement]);
	                            replaceAgent($name, $replacement, $task, $file);
			    } else {
			    $replacement = array_key_first($allAgents);
        	                    $$task[] = $replacement;
					unset($agents[$replacement]);
	                            replaceAgent($name, $replacement, $task, $file);
			    }

		}
	}

$agents = $allAgents;

}

// PRINT ACTIVE AGENTS
foreach ($agents as $name => $exp) {

echo '{agent:"' . $name . '", expertise:"' . $exp . '", phones:' .
                checkDuties($phones, $name) . ', triage:' . checkDuties($triage, $name) . ', chat:' .
                checkDuties($chat, $name) . ', sweeper:' . checkDuties($sweeper, $name) .
                ', tickets:"https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' .
                $links[$name] . '&q[]=status%3A%5B0%5D&ref=256627"},';


}


// CUSTOM FUNCTIONS
function checkDuties($duty, $name)
{
	if (in_array($name, $duty)) {
	    return 1;
	} else { return 0; }
}

function replaceAgent($name, $rand_agent, $task, $file)
{
global $weekday;
$duties = file_get_contents($file);
switch ($task) {
    case "phones":
	$task_num = 1;
        break;
    case "triage":
	$task_num = 2;
        break;
    case "chat":
	$task_num = 3;
        break;
    case "sweeper":
	$task_num = 4;
        break;
}

$pattern = '/(' . $weekday . ',.*,' . $task_num . ',.*)' . $name . '(.*)/i';
$replace = '$1' . $rand_agent . '$2';
$new = preg_replace($pattern, $replace, $duties);
file_put_contents($file, $new);

$message = "<html><body style=\"font-family:Helvetica,Arial;font-size:18px;\"><h1>AGENT REPLACEMENT</h1><b>$name</b> has been replaced by <b><u>$rand_agent</u></b> for <i>$task</i>.<br /><br />Thank you for helping out the team!<br /><br />Find new shift information here: <a href=\"https://nabasny.com/triage/daily-schedule.php\">Daily Schedule</a></body></html>";
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
$mail = new PHPMailer();
$mail->IsSMTP();
$mail->Mailer = "smtp";

$mail->SMTPAuth   = TRUE;
$mail->SMTPSecure = "tls";
$mail->Port       = 587;
$mail->Host       = "smtp.gmail.com";
$mail->Username   = getenv('TRIAGE_MBOX');
$mail->Password   = getenv('TRIAGE_PW');

$mail->IsHTML(true);
$mail->AddAddress("jake.nabasny@ingrammicro.com");
$mail->SetFrom("f14fe1b8ebb1d3e98ea8a@gmail.com", "Triage Helper");
$mail->AddReplyTo("jake.nabasny@ingrammicro.com");
$mail->Subject = "TRIAGE ALERT - " . $name;

$mail->MsgHTML($message);
if(!$mail->Send()) {
  error_log($mail);
}
}
?>
];
var table = new Tabulator("#full-table", {
    data:tabledata,
    layout:"fitColumns",
    movableColumns:true,
columns:[
        {title:"Agent", field:"agent", width:100, formatter:"link", formatterParams:{urlField:"tickets",target:"_blank",}},
        {title:"Expertise", field:"expertise", headerFilter:"input"},
        {title:"Phones", field:"phones", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Triage", field:"triage", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Chat", field:"chat", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Sweeper", field:"sweeper", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
    ],
});
</script>
<?php
if (!empty($out)) {
	echo "<div class=\"out-banner\">Currently out:  ";

	foreach ($out as $name) {
	echo '<a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' .
		$links[$name] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . $name . '</a>  ';
	}

	echo '</div>';
}
?>
<br />
<p style="font-size:small;">[am] = Agent will be available in the morning.</p>
</body>
</html>
