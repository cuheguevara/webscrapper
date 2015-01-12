<?php

function curl_get_html($options,$file){
  $ch = curl_init();
  foreach ($options as $key => $value) {
      curl_setopt($ch, $key,$value);
  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
    
  $server_output = curl_exec($ch);
  
  curl_close($ch);
  @unlink($file);
  $fp = @fopen($file, 'w');
  fwrite($fp, $server_output);
  fclose($fp);
  return $file;
}

?>


