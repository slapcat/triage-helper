<?php

// check email
$mailbox = "{localhost:993/imap/ssl/novalidate-cert}INBOX";
$inbox = imap_open($mailbox, getenv('TRIAGE_MBOX'), getenv('TRIAGE_PW')) or die('Cannot connect to email: ' . imap_last_error());

$emails = imap_search($inbox, 'ALL');

if($emails)
 {
 foreach($emails as $msg_number)
 {
	// check agent name
	$header = imap_headerinfo($inbox, $msg_number);
	$subject = $header->subject;
	$subject = ucwords($subject); // uppercase it
	//$subject = explode(' ',$subject);

	// grab agent names from CSV and load into string
	$row = 1;
	$agents = array();

	if (($handle = fopen(getenv('CSV_DL'), "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	        $num = count($data);
	        $row++;
			if (substr($data[0], 0, 1) == '#') {
			$data[0] = substr($data[0], 1);
			}
		$agents[] = $data[0];
	    }
	    fclose($handle);
	}

// find overlap between subject and agent names
// [ARRAY cannot account for spaces between first name and last initial]
//$requester = array_intersect($agents, $subject);
//$requester = array_values($requester); // reset index numbers
foreach ($agents as $name) {
	if (strpos($subject, $name) !== false) {
	    $requester = $name;
	}
}

// parse message body
$message = imap_body($inbox, $msg_number);
$message = strip_tags($message, '');
$message = preg_replace("/\s+/", " ", $message);

// grab start and end dates
$msg1 = explode('Start Time: ', $message);
$msg2 = explode(' ', $msg1[1]);

// determine date span of time off
$startDay = substr($msg2[0], 0, -5); // remove year
$endDay = substr($msg2[5], 0, -5);
$timeoff = array();
$begin = new DateTime($startDay);
$end = new DateTime($endDay);

$interval = DateInterval::createFromDateString('1 day');
$period = new DatePeriod($begin, $interval, $end);

foreach ($period as $dt) {
    $timeoff[] =  $dt->format("n/j");

}
    $timeoff[] =  $end->format("n/j");

// sed those dates into CSV

if(strpos($message, "added") !== false) {

$removeLastQuote = shell_exec('sed -i "/^\(' . $requester . '\)/s/\"$//" ' . getenv('CSV_LOCAL'));

//$file = file_get_contents(getenv('CSV_LOCAL'));

	foreach ($timeoff as $date) {
	$added = shell_exec('sed -i "\=^\(' . $requester . '\)=s=$=' . $date . ',=" ' . getenv('CSV_LOCAL'));
	//$pattern = '#^(' . $requester . '.*),"$#i';
	//echo preg_replace($pattern, '${1},' . $date . ',', $file);
	imap_delete($inbox,$msg_number);
	}

$addLastQuote = shell_exec('sed -i "/^\(' . $requester . '\)/s/$/\"/" ' . getenv('CSV_LOCAL'));

} elseif (strpos($message, "deleted") !== false) {

//$file = file_get_contents(getenv('CSV_LOCAL'));

	foreach ($timeoff as $date) {
	$deleted = shell_exec('sed -i "\=^\(' . $requester . '\)=s=' . $date . ',==" ' . getenv('CSV_LOCAL'));
	//$pattern = '#^(' . $requester . '.*)' . $date . ',(.*)$#i';
	//echo preg_replace($pattern, "$1$2", $file);
	imap_delete($inbox,$msg_number);
	}

} else {
// This is a change of date that needs to be updated manually.
}

 }
}

imap_expunge($inbox);
imap_close($inbox);
?>
