<?php

$q = $argv[1];
$key = $argv[2];
$lat = $argv[3];
$log = $argv[4];
$metric = $argv[5]; 
$url = "https://api.darkskyapp.com/v1/brief_forecast/{$key}/{$lat},{$log}";

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

if ( $metric == 'true' )
{
  $temperature = round( (5/9)*($wx->currentTemp-32) );
}else{
  $temperature = $wx->currentTemp;
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

switch ($q) {

  case "now":
  case "":

    if ($wx->currentSummary == "clear"):    
      echo "It's {$temperature} degrees with no rain. ";
      if ($wx->hourSummary == "clear"):
        echo "The next hour looks clear.";
      else: 
        if (strpos($wx->hourSummary, "min")):
          echo "The next hour looks like " . str_replace('min', 'minutes', $wx->hourSummary) . ".";
        else: 
          echo ucfirst($wx->hourSummary) . " in the next hour.";
        endif;
      endif;
      
    else:
      echo "It's {$temperature} degrees and {$wx->currentSummary}. ";
      if ($rain['until_change'] == 0):
        echo "It'll be like that for a while.";
      elseif (strpos($wx->hourSummary, "min")):
        echo "Expect " . str_replace('min', 'minutes', $wx->hourSummary) . ".";
      else:
        echo "In the next hour, expect {$wx->hourSummary}.";
      endif;
    endif;
    break;
    
  case "today":
  case "tomorrow":
    if ($wx->daySummary == "rain"):
      echo "Looks like rain in the forecast.";
    elseif (strpos($wx->daySummary, "tomorrow") || strpos($wx->daySummary, "morning") || strpos($wx->daySummary, "afternoon") || strpos($wx->daySummary, "evening")):
      if (strpos($wx->daySummary, "chance")):
        echo "It looks like a {$wx->daySummary}.";
      else:
        echo "It looks like {$wx->daySummary}.";
      endif;
    break;
    else:
      if ($wx->daySummary == "clear"):  
        echo "It looks {$wx->daySummary} in the next 24 hours.";
        return;
      endif;
      if (strpos($wx->daySummary, "chance")):  
        echo "It looks like a {$wx->daySummary} in the next 24 hours.";
      else:
        echo "It looks like {$wx->daySummary} in the next 24 hours.";
      endif;
    endif;
    break;
}
?>