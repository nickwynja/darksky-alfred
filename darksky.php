<?php
require_once('workflows.php');
$w = new Workflows();

$lat = $argv[1];
$lon = $argv[2];
$metric = $argv[3];

$key = $w->get( 'api.key', 'settings.plist' );
$url = "https://api.darkskyapp.com/v1/brief_forecast/{$key}/{$lat},{$lon}";

function get_data($url) {
  $ch = curl_init();
  $timeout = 5;
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
  $data = curl_exec($ch);
  curl_close($ch);
  return $data;
}

$r = get_data($url);
$wx = json_decode($r);
$intensity = $wx->currentIntensity;

///////////// ERROR HANDLING ////////////////

if (!is_object($wx)) {
  $error = "Invalid response - not JSON object";
} elseif ($wx == '') {
  $error = "No response. Check your internet connection.";
} elseif (!isset($wx->currentTemp) && !isset($wx->code)) { // ensure some expected data is present
  $error = "Invalid response data";
} elseif (isset($wx->code)) { // see if there was an error
  $error = "Error: $wx->error (Error Code: $wx->code)";
}

// the rest of the code relies on these being set
// if not set then PHP throws 
// Notice:  Undefined property: stdClass warnings

// currentIntensity
// currentTemp
// minutesUntilChange
// hourSummary
// currentSummary
// daySummary

// ensure more of expected data is present
elseif (!isset($wx->currentTemp) || !isset($wx->currentIntensity)) {
  $error = "Invalid response - missing currentTemp or currentIntensity";
}

if(isset($error)) {
  $w->result( 'error', 'error', 'There was an error checking your weather.', $error, 'icon.png', 'no');
  echo $w->toxml();
  die ($error . print_r($wx, 1));
}

///////////// CONVERT TO CLEANER DATA  ////////////////

if ( $metric == 'TRUE' ) {
  $temperature = round( (5/9)*($wx->currentTemp-32) );
  $scale = 'metric';
} else {
  $temperature = $wx->currentTemp;
  $scale = 'farenheight';
}

switch ($intensity){
  case ($intensity> 45):
    $how_instense = "heavy";
    break;
  case ($intensity > 30) && ($intensity < 45):
    $how_instense = "moderate";
    break;
  case ($intensity > 15) && ($intensity < 30):
    $how_instense = "light";
    break;
  case ($intensity > 2) && ($intensity < 15):
    $how_instense = "sporadic";
    break;
  }

$rain = array("intensity" => $how_instense, "until_change" => $wx->minutesUntilChange);


///////////// CHECK CURRENT ////////////////

if ($wx->currentSummary == "clear") {
  if (($scale == 'farenheight' && $temperature >= 32) || ($scale == 'metric' && $temperature >= 0)){
    $precip = 'rain';
  } else {
    $precip = 'snow';
  }
  $now = "It's {$temperature} degrees with no {$precip}.";
} else {
  $now = "It's {$temperature} degrees and {$wx->currentSummary}.";
}

$w->result( 'now', 'now', $now, 'Now', 'icon.png', 'no');

///////////// CHECK HOUR ////////////////

if (strpos($wx->hourSummary, "min")) {
  $wx->hourSummary = str_replace('min', 'minutes', $wx->hourSummary);
}

if ($wx->hourSummary == "clear") {
  $next_hour = "The next hour looks clear.";
} elseif ($rain['until_change'] == 0) {
    $next_hour = "It'll be like this for a while.";
} else {
  $next_hour = "Expect {$wx->hourSummary}.";
}

$w->result( 'next-hour', 'next-hour', $next_hour, 'Next Hour', 'icon.png','no');

///////////// CHECK 24 ////////////////

if ($wx->daySummary == "rain") {
  $next_24 = "Looks like rain in the forecast.";
} else {
  $next_24 = "Forecast is {$wx->daySummary} in the next 24 hours.";
} 

if (strpos($wx->daySummary, "chance")) {
  $next_24 = "Forecasting a {$wx->daySummary}.";
}

$w->result( 'next-24', 'next-24', $next_24, 'Next 24 Hours', 'icon.png','no');

echo $w->toxml();