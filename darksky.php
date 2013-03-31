<?php
require_once('workflows.php');
$w = new Workflows();

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

$key = $w->get( 'api.key', 'settings.plist' );

// Get location data from IP address

$ip = get_data('http://ipecho.net/plain');
$r  = get_data('http://freegeoip.net/json/'.$ip);
$l = json_decode($r);

if (!is_object($l)) {
  $w->result( 'error', 'error', 'There was an error checking your weather.', 'Unable to determine location', 'icon.png', 'no');
  echo $w->toxml();
  die ($error . print_r($l, 1));
}

// Set to metric if outside of US

$metric = $l->country_code == 'US' ? FALSE : TRUE;

// Call API URL

$url = "https://api.forecast.io/forecast/{$key}/{$l->latitude},{$l->longitude}";
$r = get_data($url);
$wx = json_decode($r);

// Error Handling

if (!is_object($wx)) {
  $error = "Invalid response - not JSON object";
} elseif ($wx == '') {
  $error = "No response. Check your internet connection.";
} elseif (!isset($wx->currently->temperature) && !isset($wx->code)) { // ensure some expected data is present
  $error = "Invalid response data";
} elseif (isset($wx->code)) { // see if there was an error
  $error = "Error: $wx->error (Error Code: $wx->code)";
} elseif (!isset($wx->currently->temperature)) { // ensure more of expected data is present
  $error = "Invalid response - missing currently->temperature";
}

if(isset($error)) {
  $w->result( 'error', 'error', 'There was an error checking your weather.', $error, 'icon.png', 'no');
  echo $w->toxml();
  die ($error . print_r($wx, 1));
}

/////////////////////////////////////////////

if ($metric === TRUE) {
  $t = round( (5/9)*($wx->currently->temperature-32) );
} else {
  $t = round($wx->currently->temperature);
}

$now = "It's {$t} degrees and " . strtolower($wx->currently->summary).'.';

if ($wx->minutely->summary == '') {
  $wx->minutely->summary = 'No data for hourly conditions.';
}

if (strpos($wx->minutely->summary, "min.")){
 $wx->minutely->summary = str_replace('min.', 'minutes', $wx->minutely->summary);
}

$w->result( '0', 'now', $now, 'Now', 'icon.png', 'no');
$w->result( '1', 'next-hour', $wx->minutely->summary, 'Next Hour', 'icon.png','no');
$w->result( '2', 'next-24', $wx->hourly->summary, 'Next 24 Hours', 'icon.png','no');

echo $w->toxml();