<?php
if($f3->get('game_integration_enable')){
    //Setup sourcequery
    $game_query = new \xPaw\SourceQuery\SourceQuery();
    
    //Set variable defaults
    $curr_server = null;

    function check_rcon_cmd($message, $trigger){
        if (count($trigger) !== 0 && is_array($trigger)){
            foreach ($trigger as $trig) {
                if (substr($message, 0, strlen($trig)) == $trig) {
                    $data = explode(" ", $message);
        
                    $trig = str_replace(".", "", $data[0]);
                    unset($data[0]);
                    $data = array_values($data);
                    $messageString = implode(" ", $data);
        
                    return array("trigger" => $trig, "messageArray" => $data, "messageString" => $messageString);
                }
            }
        }
        return false;
    }
}