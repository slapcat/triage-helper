<?php
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
<div class="title"><font size="16px">Please assign tickets to...</font>
<div class="dropdown">
  <button onclick="myFunction()" class="dropbtn">✎</button>
  <div id="myDropdown" class="dropdown-content">
    <a href="<?php echo getenv('CSV_URL') ?>" target="_blank">Schedule</a>
    <a href="<?php echo getenv('DUTIES_URL') ?>" target="_blank">Duties</a>
  </div>
</div></div>
<script>
/* When the user clicks on the button,
toggle between hiding and showing the dropdown content */
function myFunction() {
  document.getElementById("myDropdown").classList.toggle("show");
}

// Close the dropdown if the user clicks outside of it
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
</script>

<div id="full-table"></div>

<script type="text/javascript">
//sample data
var tabledata = [
<?php

// DEFINE VARIABLES
$row = 1;
$agents = "";
$out = "";
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
if (($handle = fopen(getenv('DUTIES_DL'), "r")) !== FALSE) {
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

// CHECK THE SCHEDULE
$row=1;

if (($handle = fopen(getenv('CSV_DL'), "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// DEFINE IN & OUT TIMES
	$buffertime = ($data[$timein] - 2);
	$timeout = ($data[$timein] + 8.5);

	// CHECK IF WORKING NOW
	if ($data[$weekday] == 1 && $hour >= $buffertime && $hour < $timeout) {
		if (substr($data[0], 0, 1) !== '#') {
		//list - $agents = $agents . '<br /><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank"><div class="tooltip">' . $data[0] . '<span class="tooltiptext">' . $data[11] . '</span></div></a>';
		//table - $agents = $agents . '<tr><td><a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . $data[0] . '</a></td><td>' . $data[11] . '</td></tr>';
		$agents = $agents . '{agent:"' . $data[0] . '", expertise:"' . $data[11] . '", phones:' . checkDuties($phones, $data[0]) . ', triage:' . checkDuties($triage, $data[0]) . ', chat:' . checkDuties($chat, $data[0]) . ', sweeper:' . checkDuties($sweeper, $data[0]) . '},';
		}
	}

	// CHECK IF OUT FOR THE DAY
	if (substr($data[0], 0, 1) == '#') {
		$out = $out . " " . '<a href="https://ingrammicro-assist.freshdesk.com/a/tickets/filters/search?orderBy=updated_at&orderType=desc&q[]=' . $data[10] . '&q[]=status%3A%5B0%5D&ref=256627" target="_blank">' . substr($data[0], 1) . '</a>';
	}


    }
    fclose($handle);
}

// PRINT ACTIVE AGENTS
echo $agents;

// FUNCTION FOR CHECKING CURRENT DUTIES
function checkDuties($duty, $name)
{
	if (strpos($duty, $name) !== false) {
	    return 1;
	} else { return 0; }
}
?>
];
var table = new Tabulator("#full-table", {
    data:tabledata, //assign data to table
    layout:"fitColumns", //fit columns to width of table (optional)

columns:[                 //define the table columns
        {title:"Agent", field:"agent", width:100},
        {title:"Expertise", field:"expertise", headerFilter:"input"},
        {title:"Phones", field:"phones", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Triage", field:"triage", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Chat", field:"chat", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
        {title:"Sweeper", field:"sweeper", width:100, hozAlign:"center", formatter:"tickCross", sorter:"boolean"},
	//{title:"Link", field:"link", formatter:"link", formatterParams:{target:"_blank",}},
    ],
});
</script>
<?php
if ($out != "") {
	echo "<br /><div class=\"out\"><font color=\"red\"><b>Currently out:<br />";
	echo $out . "</b></font>";
}
?>
</body>
</html>
