<?php
include_once "/opt/fpp/www/common.php";
$pluginName = basename(dirname(__FILE__));
$pluginPath = $settings['pluginDirectory']."/".$pluginName."/"; 

$logFile = $settings['logDirectory']."/".$pluginName.".log";
$messagesCsvFile = $settings['logDirectory']."/".$pluginName."-messages.csv";
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
$pluginSettings = parse_ini_file($pluginConfigFile);

#$pluginVersion = urldecode($pluginSettings['pluginVersion']);
$sodEnabled = urldecode($pluginSettings['show_on_demand_enabled']);
$sodEnabled = $sodEnabled == "true" ? true : false;

$api_base_path = "https://voip.ms/api/v1";
$oldest_message_age = 180;

$onDemandPlaylist = urldecode($pluginSettings['on_demand_playlist']);
$mainPlaylist = urldecode($pluginSettings['main_playlist']);
$voipmsApiUsername = urldecode($pluginSettings['voipms_api_username']);
$voipmsApiPassword = urldecode($pluginSettings['voipms_api_password']);
$startCommand = urldecode($pluginSettings['start_command']);
$messageSuccess = urldecode($pluginSettings['message_success']);
$messageNotStarted = urldecode($pluginSettings['message_not_started']);

if($sodEnabled == 1) {
    echo "Starting Show On Demand Plugin\n";
    logEntry("Starting Show On Demand Plugin");

    try{
        logEntry("On-demand playlist: " . $onDemandPlaylist);
        if (strlen($onDemandPlaylist)==0){
            throw new Exception('No on-demand playlist specified.');
        }

        logEntry("Main playlist: " . $mainPlaylist);
        if (strlen($mainPlaylist)==0){
            throw new Exception('No main playlist specified.');
        }

        logEntry("Voip.ms username: " . $voipmsApiUsername);
        if (strlen($voipmsApiUsername)==0){
            throw new Exception('No voip.ms username specified.');
        }

        logEntry("Voip.ms password: " . "<<redacted>>");
        if (strlen($voipmsApiPassword)==0){
            throw new Exception('No voip.ms password specified.');
        }

        logEntry("Start command: " . $startCommand);
        if (strlen($startCommand)==0){
            throw new Exception('No start command specified.');
        }

        logEntry("Success message: " . $messageSuccess);
        logEntry("Not-started message: " . $messageNotStarted);

    } catch (Exception $e) {
        logEntry($e->getMessage());
        die;
    }

    while(true) {
        try{
            $fppStatus = getFppStatus();

            if($fppStatus->scheduler->status=="playing") {
                logEntry("fpp is playing");

                $messageResponse = getMessages();
                logEntry("API Response Status: " . $messageResponse->status);

                if ($messageResponse->status == "success"){
                    $startShowForContacts = processMessages($messageResponse);

                    $shouldStart = count($startShowForContacts) > 0;

                    if ($shouldStart) {
                        $currentlyPlaying = $fppStatus->current_playlist->playlist;
                        logEntry("Currently playing: " . $currentlyPlaying);
        
                        $canStart = false;
                        if($currentlyPlaying == $onDemandPlaylist) {
                            logEntry("The on-demand playlist is playing"); 
                            $canStart = true;
                        }
                        else{
                            logEntry("The on-demand playlist is not playing");
                            $canStart = false;
                        }

                        sendResponses($startShowForContacts,$canStart);

                        if ($canStart){
                            startShow();
                        }
                    }
                    else{
                        logEntry("Nothing to do");
                    }
                }
            }else {
                logEntry("fpp is not playing");
            }
        } catch (Exception $e) {
            logEntry('Exception: ' . $e->getMessage());
        }

        logEntry("Sleeping");
        sleep(30);
    }
}else {
    logEntry("Show On Demand Plugin is disabled");
}

function startShow(){
    global $mainPlaylist;

    
    $url = "http://127.0.0.1/api/command/Insert Playlist Immediate/" . $mainPlaylist ."/0/0/true";
    $url = str_replace(' ', '%20', $url);

    logEntry("Triggering main playlist: " . $url);
    $result=file_get_contents($url);
    logEntry("Result: " . $result);
}

function processMessages($messageResponse){
    global $startCommand, $oldest_message_age;

    $shouldStart = false;

    $respondTo = array();

    foreach($messageResponse->sms as $item) {
        try{
            $id = $item->id;
            $date = $item->date;
            $did = $item->did;
            $contact = $item->contact;
            $message = trim($item->message);
            logEntry("Message ID: " . $id);

            $action = "ignored";

            $now = new DateTime('now');
            $datetime = new DateTime( $date );
            $diffInSeconds = $now->getTimestamp() - $datetime->getTimestamp();
            logEntry("Message Age: " . $diffInSeconds);

            if ($diffInSeconds > $oldest_message_age){
                $action = "too old";
            }
            else {
                if (strcasecmp($message, $startCommand) == 0) {
                    $action = "start show";
                    if ($shouldStart){
                        $action = "start show (duplicate)";
                    }
                    $shouldStart = true;
                    //array_push($respondTo, $contact);
                    $respondTo[$contact] = $did;
                }
                else{
                    logEntry("Unknown message: " . $message);
                }
            }



            saveMessageToCsv($id, $date, $did, $contact, $message, $action);

            deleteMessage($id);

        } catch (Exception $e) {
            logEntry('Failed on processing message: ' . $e->getMessage());
        }
    }

    logEntry("Will respond to: " . json_encode($respondTo));

    return $respondTo;
}

function sendResponses($contacts, $didStart){
    global $messageNotStarted,$messageSuccess;
    foreach($contacts as $destination => $did) {
        logEntry("Destination: " . $destination . "  DID: " . $did);
        
        $message = $messageNotStarted;
        if ($didStart){
            $message = $messageSuccess;
        }

        sendMessage($did, $destination, $message);
    }
}

function getMessages(){
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
        'method'=>'getSMS',
        'type'=>'1',
         )
    );
    $context = stream_context_create( $options );
    //logEntry("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logEntry("API response: " . $result);
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
    logEntry("Deleting SMS ID: " . $id);
    $context = stream_context_create( $options );
    //logEntry("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logEntry("API response: " . $result);
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
    logEntry("Sending SMS to: " . $destination);
    $context = stream_context_create( $options );
    //logEntry("API Request: " . $url ."?" .$getdata);
    $result = file_get_contents( $url ."?" .$getdata, false, $context );
    logEntry("API response: " . $result);
    return json_decode( $result );
}

function getFppStatus() {
    $result=file_get_contents("http://127.0.0.1/api/fppd/status");
    return json_decode( $result );
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