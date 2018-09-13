<?php

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

$stmt = $DB->prepare("SELECT `name` FROM ".$table." WHERE `birthday`=?");
date_default_timezone_set('America/Los_Angeles');
$today = date("m-d");
echo("Testing for birthdays on ".$today."\n");
$stmt->bind_param("s",$today);
$stmt->execute();
$stmt->bind_result($name);
$birthdays = array();
while($stmt->fetch()) {
    $birthdays[] = $name;
}
$stmt->close();

if(count($birthdays) === 0) {
    die("No birthdays today!\n");
}

foreach($birthdays as $bday) {
    $message = array("text"=>"*Happy Birthday ".$bday."!*");
    echo("It's ".$bday."'s birthday\n");
    postToSlack(json_encode($message));
}
postToSlack(json_encode(array("text"=>":tada::tada::tada:")));

?>