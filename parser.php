<?php

// check email
$mailbox = "{imap.gmail.com:993/imap/ssl}INBOX";
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
	$out = array();
	$agents = array();

	if (($handle = fopen(getenv('CSV_LOCAL'), "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	        $num = count($data);
	        $row++;
			if (substr($data[0], 0, 1) == '#') {
			$data[0] = substr($data[0], 1);
			$out[] = $data[0];
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



// add back # if agent currently out
if (in_array($requester, $out)) {
    $requester = '#' . $requester;
}

// sed those dates into CSV

if(strpos($message, "added") !== false) {

$file = file_get_contents(getenv('CSV_LOCAL'));

	$file = preg_replace('@(^'.$requester.'.*)".*$@m', '${1}', $file);

	foreach ($timeoff as $date) {
	$file = preg_replace('@(^'.$requester.'.*).*$@m', '${1}'.$date.',', $file);
	}

	$file = preg_replace('@(^'.$requester.'.*),$@m', '${1},"', $file);

file_put_contents(getenv('CSV_LOCAL'), $file);
imap_delete($inbox,$msg_number);

} elseif (strpos($message, "deleted") !== false) {

$file = file_get_contents(getenv('CSV_LOCAL'));

	foreach ($timeoff as $date) {
	$pattern = '@(^'.$requester.'.*)'.$date.',(.*)@m';
	$file = preg_replace($pattern, '${1}${2}', $file);
	}

file_put_contents(getenv('CSV_LOCAL'), $file);
imap_delete($inbox,$msg_number);

} else {
// This is a change of date that needs to be updated manually.
}

 }
}

imap_expunge($inbox);
imap_close($inbox);
?>