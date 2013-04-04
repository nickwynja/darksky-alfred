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

// Get api key and theme from settings file
$key = $w->get( 'api.key', 'settings.plist' );
$theme = $w->get('theme', 'settings.plist');
$unit_code = $w->get( 'units', 'settings.plist' );
$location = $w->get('location', 'settings.plist');

// Check api key, die if malformed or missing
if (preg_match('/[0-9a-f]{32}/',$key) != 1) {
	$error = "Missing or incorrect API key.";
	$w->result( 'error', 'error', 'There was an error with your API key.', 'Set API key by typing "w k " and following instructions', 'icon.png', 'no');
	echo $w->toxml();
	die ($error . print_r($l, 1));	
}

// Set path to theme icons
$icon_path = 'icons/'.$theme.'/';

if (!$location) {
	// Get location data from IP address
	$ip = get_data('http://ipecho.net/plain');
	$r  = get_data('http://freegeoip.net/json/'.$ip);
	$l = json_decode($r);
} else {
    $loc = explode(',', $location, 2);
    $l->latitude = $loc[0];
    $l->longitude = $loc[1];
}

// Die if unable to determine location
if (!is_object($l)) {
	$error = "Unable to determine location";
	$w->result( 'error', 'error', 'There was an error checking your weather.', 'Unable to determine location', 'icon.png', 'no');
	echo $w->toxml();
	die ($error . print_r($l, 1));
}

if ($unit_code == 'us' || $unit_code == 'uk' || $unit_code == 'si') {
	$units = $unit_code;
} else {
	// Set units based on country code from freegeoip if set to default
	$units = $l->country_code == 'US' || $l->country_code == 'UK' ? strtolower($l->country_code) : 'si';
}

// Call API URL
$url = "https://api.forecast.io/forecast/{$key}/{$l->latitude},{$l->longitude}?units={$units}";
$r = get_data($url);
$wx = json_decode($r);
$web_url = "http://forecast.io/#/f/{$l->latitude},{$l->longitude}";

// Error Handling from API
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

// Format JSON results nicely. There's some extra variables in here in case I might use them in the future.

// Set time zone based on results for proper time formatting
$timezone = $wx->timezone;
date_default_timezone_set($timezone);
$current_time = $wx->currently->time;

// Summary for right now
$now_temp = round($wx->currently->temperature);
$now = "It’s {$now_temp}° and " . strtolower($wx->currently->summary).' right now.';
$now_icon = $icon_path.$wx->currently->icon.'.png';
$now_next = $wx->minutely->summary . " " . $wx->hourly->summary;

// Summary for today
$today = $wx->daily->data[0]->summary;
$today_icon = $icon_path.$wx->daily->data[0]->icon.'.png';
$today_high = round($wx->daily->data[0]->temperatureMax);
$today_low = round($wx->daily->data[0]->temperatureMin);
$today_high_time = date('g:ia',$wx->daily->data[0]->temperatureMaxTime);
$today_low_time = date('g:ia',$wx->daily->data[0]->temperatureMinTime);
$today_temps = "High around {$today_high}° near {$today_high_time}.";

// Summary for tomorrow
$tomorrow = $wx->daily->data[1]->summary;
$tomorrow = substr($tomorrow, 0,strlen($tomorrow)-1)." tomorrow.";
$tomorrow_icon = $icon_path.$wx->daily->data[1]->icon.'.png';
$tomorrow_high = round($wx->daily->data[1]->temperatureMax);
$tomorrow_low = round($wx->daily->data[1]->temperatureMin);
$tomorrow_high_time = date('g:ia',$wx->daily->data[1]->temperatureMaxTime);
$tomorrow_low_time = date('g:ia',$wx->daily->data[1]->temperatureMinTime);
$tomorrow_temps = "Low around {$tomorrow_low}° and high around {$tomorrow_high}°.";

// Summary for near future
$future = $wx->daily->summary;
$future_icon = $icon_path.$wx->daily->icon.'.png';
$future_ar = explode(";",$future);

// If it's currentl after sunset, show more relevant temperature data for today & tomorrow
if($current_time > $wx->daily->data[0]->sunsetTime) {
	$today_temps = "Low overnight around {$tomorrow_low}° near {$tomorrow_low_time}.";
	$tomorrow_temps = "High around {$tomorrow_high}° near {$tomorrow_high_time}.";
	
}

if($current_time < $wx->daily->data[0]->sunriseTime) {
	$today_temps = "Low around {$today_low}° near {$today_low_time} and high around {$today_high}° near {$today_high_time}";	
}

// Expand 'min.' to 'minutes' in $now, just in case.
if (strpos($now, "min.")){
 $now = str_replace('min.', 'minutes', $now);
}

// Prepare results for display
$w->result( 'now', $web_url, $now, $now_next, $now_icon, 'yes');
$w->result( 'today', $web_url, $today, $today_temps, $today_icon,'yes');
$w->result( 'tomorrow', $web_url, $tomorrow, $tomorrow_temps, $tomorrow_icon,'yes');
$w->result( 'future', $web_url, $future_ar[0].".", ucfirst(ltrim($future_ar[1])), $future_icon,'yes');

// Place holder to show any test data for debugging
// Left in for anyone who wants to tweak ;)
/*
$w->result(
	'test',
	'test',
	'Test Data',
	'',
	'icon.png',
	'no');
*/

// Return results to Alfred as XML
echo $w->toxml();
