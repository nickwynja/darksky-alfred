<?php

require_once('workflows.php');
$w = new Workflows();
$current_version = '2.0';
$latitude = isset($argv[1]) ? $argv[1] : '' ; // Index 0 is 'darksky.php'
$longitude = isset($argv[2]) ? $argv[2] : '';
$user_unit = isset($argv[3]) ? $argv[3] : '' ;

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

// Initialize Settings

$s = 'settings.plist';
$key = $w->get( 'api_key', $s );

if (!$w->get( 'check_for_updates', $s )) {
  $w->set( 'check_for_updates', 'TRUE' , $s);
}

if (!$w->get( 'update_ignore_date', $s )) {
  $w->set( 'update_ignore_date', time() , $s);
}

// Check for updates. Initially had coded for checking once a day but Alfred initiated duplicate calls which was screwing up the timing. This check isn't expensive so I'll just do it every time.

if (($w->get( 'check_for_updates', $s ) == 'TRUE')) {
  $now = time();
  $r = get_data('https://raw.github.com/nickwynja/darksky-alfred/master/version.json');
  $v = json_decode($r);

  $update_version = (float) $v->updated_version;
  $update_ignore_date = (int) $w->get( 'update_ignore_date', $s );
  $since_update_ignored = $now - $update_ignore_date;

  if (($update_version > $current_version) && ($since_update_ignored > 259200)) {
    $w->result( 'download', $v->download_url, "Version {$v->updated_version} of Dark Sky is available. Update now?", "This will take you to the download page.", 'icon.png');
    $w->result( 'dont-update', 'dont-update', "No thanks. Tell me what the weather's like...", "Check the forcast and ignore updates for a few days.", 'icon.png');
    $w->result( 'never-update', 'never-update', "Don't ever check for updates.", "Just show me the forecast.", 'icon.png');
    echo $w->toxml();
    die;
  }
}

// Check for user set variables

if (($latitude !== 'FALSE' ) && ($longitude !== 'FALSE')) {

  $l->latitude = $latitude;
  $l->longitude = $longitude;

} else {

  // Get location data from IP address

  $ip = get_data('http://ipecho.net/plain');
  $r  = get_data('http://freegeoip.net/json/'.$ip);
  $l = json_decode($r);

  if (!is_object($l)) {
    $w->result( 'error', 'error', 'There was an error checking your weather.', 'Unable to determine location', 'icon.png', 'no');
    echo $w->toxml();
    die ($error . print_r($l, 1));
  }
}

if (!empty($user_unit)) {

$unit = $user_unit;

} else {

$unit = 'auto';

}

// Call API URL

$url = "https://api.forecast.io/forecast/{$key}/{$l->latitude},{$l->longitude}?units={$unit}";
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

$t = round($wx->currently->temperature);

$now = "It's {$t} degrees and " . strtolower($wx->currently->summary).'.';

if (empty($wx->minutely->summary)) {
  $wx->minutely->summary =  $wx->hourly->data[0]->summary;
}

if (empty($wx->hourly->summary)) {
  $wx->hourly->summary =  $wx->daily->data[0]->summary;
}

if (strpos($wx->minutely->summary, "min.")){
 $wx->minutely->summary = str_replace('min.', 'minutes', $wx->minutely->summary);
}

$w->result( '1', 'now', $now, 'Now', 'icon.png', 'no');
$w->result( '2', 'next-hour', $wx->minutely->summary, 'Next Hour', 'icon.png','no');
$w->result( '3', 'next-24', $wx->hourly->summary, 'Next 24 Hours', 'icon.png','no');

echo $w->toxml();