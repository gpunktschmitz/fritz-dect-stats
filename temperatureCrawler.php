<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__) . DS);

date_default_timezone_set('Europe/Berlin');

require('login.inc.php');

$ch = curl_init('http://fritz.box/login_sid.lua');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$login = curl_exec($ch);
$session_status_simplexml = simplexml_load_string($login);
if($session_status_simplexml->SID != '0000000000000000') {
	$SID = $session_status_simplexml->SID;
} else {
	$challenge = $session_status_simplexml->Challenge;
	$response = $challenge . '-' . md5(mb_convert_encoding($challenge.'-'.$fritzbox_password, "UCS-2LE", "UTF-8"));
	curl_setopt($ch, CURLOPT_POSTFIELDS, "username=".$fritzbox_username."&response={$response}&page=/login_sid.lua");
	$sendlogin = curl_exec($ch);
	$session_status_simplexml = simplexml_load_string($sendlogin);
	if($session_status_simplexml->SID != '0000000000000000') {
		$SID = $session_status_simplexml->SID;
	} else {
		echo "ERROR: Login failed";
		return;
	}
}
curl_close($ch);

function getTemperatureArray($SID) {
	$retArr = Array();
	$devicesList = rtrim(file_get_contents('http://fritz.box/webservices/homeautoswitch.lua?switchcmd=getswitchlist&sid='.$SID));
	$devicesArray = explode(',',$devicesList);
	foreach($devicesArray as $device) {
		$switchPresent = intval(rtrim(file_get_contents('http://fritz.box/webservices/homeautoswitch.lua?switchcmd=getswitchpresent&ain='.$device.'&sid='.$SID)));
		if($switchPresent) {
			$arr = Array();
			$arr['name'] = rtrim(file_get_contents('http://fritz.box/webservices/homeautoswitch.lua?switchcmd=getswitchname&ain='.$device.'&sid='.$SID));
			$arr['identifier'] = $device;
			$arr['temperature'] = rtrim(file_get_contents('http://fritz.box/webservices/homeautoswitch.lua?switchcmd=gettemperature&ain='.$device.'&sid='.$SID));
			$arr['time'] = $date = date('H:i', time());
		}
		$retArr[] = $arr;
	}
	return $retArr;
}

function returnArrayStringSeparated(Array $arr, $addLineBreak = FALSE, $saveKey = FALSE, $prepend = '') {
	$str = '';
	foreach($arr as $key=>$value) {
		if($prepend) {
			$str .= $prepend . ';';
		}
		
		if($saveKey) {
			$str .= $key . ';' . $value . ';';
		} else {
			$str .= $value . ';';
		}
		
		if($addLineBreak) {
			$str .= "\n";
		}
	}
	
	if(!$addLineBreak) {
		$str .= "\n";
	}
	return $str;
}

function appendStringToFile($filename, $string) {
	$fh = fopen($filename, 'a');
	//$string = $string . "\n";
	fwrite($fh, $string);
	fclose($fh);
}

function returnMinimumValueArray(Array $yesterdaysArray) {
	$array = Array();
	foreach($yesterdaysArray as $line) {
		if(!empty($line)) {
			$lineArray = explode(';', $line);
			$name = $lineArray[0];
			$temperature = $lineArray[2];
			if(!array_key_exists($name, $array)) {
				$array[$name] = $temperature;
			} else {
				if($temperature < $array[$name]) {
					$array[$name] = $temperature;
				}
			}
		}
	}
	return $array;
}

function returnMaximumValueArray(Array $yesterdaysArray) {
	$array = Array();
	foreach($yesterdaysArray as $line) {
		if(!empty($line)) {
			$lineArray = explode(';', $line);
			$name = $lineArray[0];
			$temperature = $lineArray[2];
			if(!array_key_exists($name, $array)) {
				$array[$name] = $temperature;
			} else {
				if($temperature > $array[$name]) {
					$array[$name] = $temperature;
				}
			}
		}
	}
	return $array;
}

$today = date('Y-m-d', time());
$dataDir = ROOT . 'data' . DS;
$filenameToday = $dataDir . $today . '.csv';

$filenameMinimum = $dataDir . 'minimum.csv';
$filenameMaximum = $dataDir . 'maximum.csv';

//if todays file does not exist, get max and min from yesterday
if(!file_exists($filenameToday)) {
	$yesterday = date('Y-m-d', strtotime('-1 days'));
	$filenameYesterday = $dataDir . $yesterday . '.csv';
	if(file_exists($filenameYesterday)) {
		//get yesterdays data
		$yesterdaysData = file_get_contents($filenameYesterday);
		$yesterdaysArray = explode("\n", $yesterdaysData);
			
		//get minimum values and save them to $filenameMinimum
		$minArray = returnMinimumValueArray($yesterdaysArray);
		$minString = returnArrayStringSeparated($minArray, TRUE, TRUE, $yesterday);
		appendStringToFile($filenameMinimum, $minString);
			
		//get maximum values and save them to $filenameMaximum
		$maxArray = returnMaximumValueArray($yesterdaysArray);
		$maxString = returnArrayStringSeparated($maxArray, TRUE, TRUE, $yesterday);
		appendStringToFile($filenameMaximum, $maxString);
	}
}

$temperatureArray = getTemperatureArray($SID);
foreach($temperatureArray as $array) {
	$outputString = returnArrayStringSeparated($array);
	
	//store $outputString in $filenameToday
	appendStringToFile($filenameToday, $outputString);
}

$logout = file_get_contents('http://fritz.box/login.lua?page=/home/home.lua&logout=1&sid='.$SID);

?>