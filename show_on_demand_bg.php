<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginPath = $settings['pluginDirectory']."/".$pluginName."/"; 

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
#$pluginSettings = parse_ini_file($pluginConfigFile);

#$pluginVersion = urldecode($pluginSettings['pluginVersion']);


echo "Starting Show On Demand Plugin v\n";
logEntry("Starting Show On Demand Plugin v");

function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

?>