<?php
$config = parse_ini_file("settings.ini", TRUE);

$file = file_get_contents($config["db"]["schedule"]);

// RESET THE OUT LIST && REMOVE OLD VACATION DAYS
$file = preg_replace('@^#(.*$)@m', '${1}', $file);

$yesterday = date("n/j",strtotime("-1 days")) . ",";
$file = preg_replace('@'.$yesterday.'@m', '', $file);

$save = file_put_contents($config["db"]["schedule"], $file);

// FIND WHO IS OUT
$row = 1;
$out = array();

if (($handle = fopen($config["db"]["schedule"], "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// CHECK DATES
	$dates = explode(",", $data[12]);
	$today = date("n/j");

	for ($i = 0; $i < count($dates); $i++) {
		if ($dates[$i] == $today) {
			$out[] = $data[0];
		}
	}
    }
    fclose($handle);
}


// MARK OUT
foreach ($out as $name) {
	$name = escapeshellcmd($name);
	$file = preg_replace('@(^'.$name.'.*$)@m', '#${1}', $file);
}

$save = file_put_contents($config["db"]["schedule"], $file);
?>
