<?php
// RESET THE OUT LIST
$reset = shell_exec('sed -i "s/#//" /var/www/html/nextcloud/data/jake/files/IMCCS_hours.csv');


// FIND WHO IS OUT
$row = 1;
$out = array();

if (($handle = fopen("https://box.nabasny.com/index.php/s/3swmBMxZYEZaB2f/download/IMCCS_hours.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $row++;

	// CHECK DATES
	$dates = explode(",", $data[12]);
	$today = date("j/n");

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
	$set = shell_exec('sed -i "s/' . $name . '/#' . $name . '/" /var/www/html/nextcloud/data/jake/files/IMCCS_hours.csv');
}
?>
