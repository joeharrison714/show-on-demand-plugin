<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginPath = $settings['pluginDirectory']."/".$pluginName."/"; 

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$messagesCsvFile = $settings['logDirectory']."/".$pluginName."-messages.csv";
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
$pluginSettings = parse_ini_file($pluginConfigFile);

$logLevel = "INFO"; #"DEBUG"
$sleepTime = 5;

#$pluginVersion = urldecode($pluginSettings['pluginVersion']);
$sodEnabled = $pluginSettings['show_on_demand_enabled'];
$sodEnabled = $sodEnabled == "true" ? true : false;

$api_base_path = "https://voip.ms/api/v1";
$oldest_message_age = 180;
$last_processed_message_date = (new DateTime())->setTimestamp(0);

$onDemandPlaylist = $pluginSettings['on_demand_playlist'];
$mainPlaylist = $pluginSettings['main_playlist'];
$voipmsApiUsername = $pluginSettings['voipms_api_username'];
$voipmsApiPassword = $pluginSettings['voipms_api_password'];
$voipmsDid = $pluginSettings['voipms_did'];
$startCommand = $pluginSettings['start_command'];
$messageSuccess = $pluginSettings['message_success'];
$messageNotStarted = $pluginSettings['message_not_started'];

if($sodEnabled == 1) {
    echo "Starting Show On Demand Plugin\n";
    logInfo("Starting Show On Demand Plugin");

    try{
        logInfo("On-demand playlist: " . $onDemandPlaylist);
        if (strlen($onDemandPlaylist)==0){
            throw new Exception('No on-demand playlist specified.');
        }

        logInfo("Main playlist: " . $mainPlaylist);
        if (strlen($mainPlaylist)==0){
            throw new Exception('No main playlist specified.');
        }

        logInfo("Voip.ms username: " . $voipmsApiUsername);
        if (strlen($voipmsApiUsername)==0){
            throw new Exception('No voip.ms username specified.');
        }

        logInfo("Voip.ms password: " . "<<redacted>>");
        if (strlen($voipmsApiPassword)==0){
            throw new Exception('No voip.ms password specified.');
        }

        logInfo("Voip.ms DID: " . $voipmsDid);

        logInfo("Start command: " . $startCommand);
        if (strlen($startCommand)==0){
            throw new Exception('No start command specified.');
        }

        logInfo("Success message: " . $messageSuccess);
        logInfo("Not-started message: " . $messageNotStarted);

    } catch (Exception $e) {
        logInfo($e->getMessage());
        die;
    }

    while(true) {
        try{
            $fppStatus = getFppStatus();

            if($fppStatus->scheduler->status=="playing") {
                logDebug("fpp is playing");

                $messageResponse = getMessages();
                logDebug("API Response Status: " . $messageResponse->status);

                if ($messageResponse->status == "success"){
                    $startShowForContacts = processMessages($messageResponse);

                    $shouldStart = count($startShowForContacts) > 0;

                    if ($shouldStart) {
                        $currentlyPlaying = $fppStatus->current_playlist->playlist;
                        logInfo("Currently playing: " . $currentlyPlaying);
        
                        $canStart = false;
                        if($currentlyPlaying == $onDemandPlaylist) {
                            logInfo("The on-demand playlist is playing"); 
                            $canStart = true;
                        }
                        else{
                            logInfo("The on-demand playlist is not playing");
                            $canStart = false;
                        }

                        sendResponses($startShowForContacts,$canStart);

                        if ($canStart){
                            startShow();
                        }
                    }
                    else{
                        logDebug("Nothing to do");
                    }
                }
            }else {
                logDebug("fpp is not playing");
            }
        } catch (Exception $e) {
            logInfo('Exception: ' . $e->getMessage());
        }

        logDebug("Sleeping");
        sleep(5);
    }
}else {
    logInfo("Show On Demand Plugin is disabled");
}

function startShow(){
    global $mainPlaylist;

    
    $url = "http://127.0.0.1/api/command/Insert Playlist Immediate/" . $mainPlaylist ."/0/0/true";
    $url = str_replace(' ', '%20', $url);

    logInfo("Triggering main playlist: " . $url);
    $result=file_get_contents($url);
    logInfo("Result: " . $result);
}

function processMessages($messageResponse){
    global $startCommand, $oldest_message_age, $last_processed_message_date;

    $shouldStart = false;

    $respondTo = array();

    foreach($messageResponse->sms as $item) {
        try{
            $id = $item->id;
            $date = $item->date;
            $did = $item->did;
            $contact = $item->contact;
            $message = trim($item->message);
            logDebug("Message ID: " . $id);

            $action = "ignored";

            $now = new DateTime('now');
            $datetime = new DateTime( $date );
            $diffInSeconds = $now->getTimestamp() - $datetime->getTimestamp();
            logDebug("Message Age: " . $diffInSeconds);
            logDebug("Last Processed Message Date: " . $last_processed_message_date->format('Y-m-d H:i:s'));

            if ($diffInSeconds > $oldest_message_age){
                $action = "too old";
                logDebug("Message older than oldest age");
            }
            elseif($datetime <= $last_processed_message_date){
                $action = "too old";
                logDebug("Message older than last processed message date");
            }
            else {
                if (strcasecmp($message, $startCommand) == 0) {
                    $action = "start show";
                    if ($shouldStart){
                        $action = "start show (duplicate)";
                    }
                    $shouldStart = true;
                    $respondTo[$contact] = $did;
                }
                else{
                    logInfo("Unknown message: " . $message);
                }

                saveMessageToCsv($id, $date, $did, $contact, $message, $action);
            }

            if ($datetime > $last_processed_message_date){
                $last_processed_message_date = $datetime;
                logDebug("Setting Last Processed Message Date to " . $last_processed_message_date->format('Y-m-d H:i:s'));
            }
            else{
                logDebug("not greater than");
            }

            //deleteMessage($id);

        } catch (Exception $e) {
            logInfo('Failed on processing message: ' . $e->getMessage());
        }
    }

    logInfo("Will respond to: " . json_encode($respondTo));

    return $respondTo;
}

function sendResponses($contacts, $didStart){
    global $messageNotStarted,$messageSuccess;
    foreach($contacts as $destination => $did) {
        logInfo("Destination: " . $destination . "  DID: " . $did);
        
        $message = $messageNotStarted;
        if ($didStart){
            $message = $messageSuccess;
        }

        sendMessage($did, $destination, $message);
    }
}

function getMessages(){
    global $api_base_path,$voipmsApiUsername,$voipmsApiPassword,$voipmsDid;
    $url = $api_base_path . "/rest.php";
    $options = array(
        'http' => array(
        'method'  => 'GET'
        )
    );

    $paramsArray = array(
        'api_username' => $voipmsApiUsername,
        'api_password' => $voipmsApiPassword,
        'method'=>'getSMS',
        'type'=>'1',
    );
    if (strlen($voipmsDid) > 0){
        $paramsArray["did"] = $voipmsDid;
    }

    $getdata = http_build_query(
        $paramsArray
    );
    $context = stream_context_create( $options );
    logDebug("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logInfo("API response: " . $result);
    return json_decode( $result );
}

function deleteMessage($id){
    global $api_base_path,$voipmsApiUsername,$voipmsApiPassword;
    $url = $api_base_path . "/rest.php";
    $options = array(
        'http' => array(
        'method'  => 'GET'
        )
    );
    $getdata = http_build_query(
        array(
        'api_username' => $voipmsApiUsername,
        'api_password' => $voipmsApiPassword,
        'method'=>'deleteSMS',
        'id'=>$id,
         )
    );
    logInfo("Deleting SMS ID: " . $id);
    $context = stream_context_create( $options );
    logDebug("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logDebug("API response: " . $result);
    return json_decode( $result );
}

function sendMessage($did, $destination, $message){
    global $api_base_path,$voipmsApiUsername,$voipmsApiPassword;
    $url = $api_base_path . "/rest.php";
    $options = array(
        'http' => array(
        'method'  => 'GET'
        )
    );
    $getdata = http_build_query(
        array(
        'api_username' => $voipmsApiUsername,
        'api_password' => $voipmsApiPassword,
        'method'=>'sendSMS',
        'did'=>$did,
        'dst'=>$destination,
        'message'=>$message
         )
    );
    logInfo("Sending SMS to: " . $destination);
    $context = stream_context_create( $options );
    logDebug("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logDebug("API response: " . $result);
    return json_decode( $result );
}

function getFppStatus() {
    $result=file_get_contents("http://127.0.0.1/api/fppd/status");
    return json_decode( $result );
  }


function logDebug($data){
    global $logLevel;
    if ($logLevel == "DEBUG"){
        logEntry($data);
    }
}
function logInfo($data){
    global $logLevel;
    if ($logLevel == "INFO" || $logLevel == "DEBUG"){
        logEntry($data);
    }
}
function logEntry($data) {

	global $logFile,$myPid;

	$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

function saveMessageToCsv($id, $date, $did, $contact, $message, $action) {

    global $messagesCsvFile;
    
    if (!file_exists($messagesCsvFile)) {
        $csvHeaderWrite= fopen($messagesCsvFile, "a") or die("Unable to open file!");
        fwrite($csvHeaderWrite, "id,date,did,contact,message,action" ."\n");
        fclose($csvHeaderWrite);
    }

    $esc = str_replace("\"","\"\"",$message);
	
	$csvWrite= fopen($messagesCsvFile, "a") or die("Unable to open file!");
	fwrite($csvWrite, $id . "," . $date . "," . $did . "," . $contact . "," . "\"" . $esc . "\"" . "," . $action . "\n");
	fclose($csvWrite);
}

?>