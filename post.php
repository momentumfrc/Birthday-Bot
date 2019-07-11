<?php

function ordinal($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number%100) <= 13))
        return $number. 'th';
    else
        return $number. $ends[$number % 10];
}

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', TRUE);

if (! (php_sapi_name() == "cli")) {
    http_response_code(403);
    die('<html><body><p>This file is meant to be run from the command-line by crontab, not by '.php_sapi_name().'</p></body></html>');
}
require_once 'vars.php';
function postToSlack($json) {
    global $posturl;
    $ch = curl_init($posturl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$DB = new mysqli("127.0.0.1", $DBUser, $DBPass, $Database);
if($DB->connect_error) {
    die("Connection failed: ".$DB->connect_error);
}

$stmt = $DB->prepare("SELECT `name`, `year`, `slackid` FROM ".$table." WHERE `birthday`=?");
if($stmt === FALSE) {
    throw new Exception('SQL ERROR: '.$DB->error);
}
date_default_timezone_set('America/Los_Angeles');
$today = date("m-d");
$todayyear = date("Y");
echo("Testing for birthdays on ".$today.": ");
$stmt->bind_param("s",$today);
$stmt->execute();
$stmt->bind_result($name, $year, $slackid);
$birthdays = array();
while($stmt->fetch()) {
    $birthdays[] = array("name"=>$name, "year"=>$year, "slackid"=>$slackid);
}
$stmt->close();

if(count($birthdays) === 0) {
    die("No birthdays today!\n");
}

foreach($birthdays as $bday) {
    $message = "*Happy ";
    if(isset($bday["year"])) {
        $age = $todayyear - $bday["year"];
        $message .= ordinal($age);
        $message .= ' ';
    }
    $message .= 'Birthday ';
    if(isset($bday["slackid"])) {
        $message .= '<@'.$bday["slackid"].'>';
    } else {
        $message .= $bday["name"];
    }
    $message .= '!*';
    echo("It's ".$bday["name"].'\'s birthday! ');
    postToSlack(json_encode(array("text"=>$message)));
}

echo("\n");

postToSlack(json_encode(array("text"=>":tada::tada::tada:")));

?>
