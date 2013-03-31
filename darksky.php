<?php

# This API key is mine but I'll let you use it for now.
# If this stops working, get your own at
# https://developer.darkskyapp.com/register

$key = "82666152603d0d3d58296426c96119e5";

# Look up your location at
# http://stevemorse.org/jcal/latlon.php
# Don't use more than 4 decimal points

$lat = "40.7214";
$lon = "-73.9779";

# Set METRIC to true to return temperature in celcius. Otherwise returns in fahrenheit

$metric = "FALSE";

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

if ( $metric == 'TRUE' )
{
  $temperature = round( (5/9)*($wx->currentTemp-32) );
  $scale = 'metric';
}else{
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

if ($wx == '') {
 echo "<item uid='error' arg='error' valid='no'>
        <title>No response from Dark Sky. Check your Internet connection.</title>
        <subtitle>Oops</subtitle>
        <icon>icon.png</icon>
      </item>
      </items>";
return;
}

if ($wx->currentSummary == "clear") {
  if (($scale == 'farenheight' && $temperature >= 32) || ($scale == 'metric' && $temperature >= 0)){
    $precip = 'rain';
  } else {
    $precip = 'snow';
  }

  $now = "It's {$temperature} degrees with no {$precip}.";

  if ($wx->hourSummary == "clear") {
    $next_hour = "The next hour looks clear.";
  } else {
    if (strpos($wx->hourSummary, "min")) {
      $next_hour = "The next hour looks like " . str_replace('min', 'minutes', $wx->hourSummary) . ".";
    } else {
      $next_hour =  ucfirst($wx->hourSummary) . " in the next hour.";
    }
  }

} else {

  $now = "It's {$temperature} degrees and {$wx->currentSummary}.";

  if ($rain['until_change'] == 0) {
    $next_hour = "It'll be like this for a while.";
  } elseif (strpos($wx->hourSummary, "min")) {
    $next_hour = "Expect " . str_replace('min', 'minutes', $wx->hourSummary) . ".";
  } else {
    $next_hour = "In the next hour, expect {$wx->hourSummary}.";
  }
}

if ($wx->daySummary == "rain") {
  $next_24 = "Looks like rain in the forecast.";
} elseif (strpos($wx->daySummary, "tomorrow") || strpos($wx->daySummary, "morning") || strpos($wx->daySummary, "afternoon") || strpos($wx->daySummary, "evening")) {
  if (strpos($wx->daySummary, "chance")) {
    $next_24 = "Forecasting a {$wx->daySummary}.";
  } else {
    $next_24 = "Forecasting {$wx->daySummary}.";
  }
  if (strpos($wx->daySummary, "chance")) {
    $next_24 = "Forecasting a {$wx->daySummary}.";
  } else {
    $next_24 = "Forecast is {$wx->daySummary}.";
  }
} else {
  if ($wx->daySummary == "clear") {
    $next_24 = "Forecast is {$wx->daySummary} in the next 24 hours.";
  }
}

echo "<?xml version='1.0'?>
      <items>
        <item uid='now' arg='now' valid='no'>
          <title>{$now}</title>
          <subtitle>Now</subtitle>
          <icon>icon.png</icon>
        </item>
        <item uid='next-hour' arg='next-hour' valid='no'>
          <title>{$next_hour}</title>
          <subtitle>Next Hour</subtitle>
          <icon>icon.png</icon>
        </item>
        <item uid='next-24' arg='next-24' valid='no'>
          <title>{$next_24}</title>
          <subtitle>Next 24 Hours</subtitle>
          <icon>icon.png</icon>
        </item>
      </items>";