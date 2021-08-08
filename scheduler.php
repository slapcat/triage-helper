<?php
// RESET THE OUT LIST
$reset = shell_exec('sed -i "s/#//" ' . getenv('CSV_LOCAL'));
$yesterday = date("n/j",strtotime("-1 days")) . ",";
$removeOldDays = shell_exec('sed -i "s=' . $yesterday  . '==g" ' . getenv('CSV_LOCAL'));

// FIND WHO IS OUT
$row = 1;
$out = array();

if (($handle = fopen(getenv('CSV_DL'), "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// CHECK DATES
	$dates = explode(",", $data[12]);
	$today = date("n/j");

	for ($i = 0; $i < count($dates); $i++) {
		if (strpos($dates[$i], $today) !== false) {
			$out[] = $data[0];
		}
	}
    }
    fclose($handle);
}


// MARK OUT
foreach ($out as $name) {
	$name = escapeshellcmd($name);
	$name = str_replace(' ', '', $name);
	$set = shell_exec('sed -i "s/' . $name . '/#' . $name . '/" ' . getenv('CSV_LOCAL'));
}
?>
