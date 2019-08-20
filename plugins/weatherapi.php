<?php

class weatherapi{
    /**
     * @var
     */
    var $f3;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;

    /**
     * @param $f3
     * @param $discord
     * @param $logger
     */
    function init($f3, $discord, $logger){
        $this->f3 = $f3;
        $this->discord = $discord;
        $this->logger = $logger;
    }

    function onLog(){
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData){
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        $fromName = $msgData["message"]["from"];
        
        //Process trigger and resolve command/argument parts
        $command = processTrigger($message, get_plugin_commands($this));
        $msgarg = $command['argument'];
        
        //Set the guild ID for server or PM
        if ($msgData["guild"]["id"] !== null){
            $guildID = $msgData["guild"]["id"];
            $reply_to = $channelID;
        }else{
            $guildID = null;
            $reply_to = $fromID;
        }
        
        //Check if trigger was processed
        if(isset($command['command'])){
            //Check if message argument was passed
            if($msgarg !== ''){
                //Check if zip or city name
                if(is_numeric($msgarg)){
                    $cityzip = "zip=";
                }else{
                    $cityzip = "q=";
                }
                
                //Get weather data
                $client = new GuzzleHttp\Client();
                try {
                    $body = $client->get('http://api.openweathermap.org/data/2.5/weather?'.$cityzip.$msgarg.'&APPID='.$this->f3->get('weather_api_key').'&units=imperial')->getBody();    
                }
                catch (Exception $e) {
                    $response = $e->getResponse();
                    $responseBodyAsString = $response->getBody()->getContents();
                    dump($responseBodyAsString);
                }
                
                $return = json_decode($body);
                
                //Check if respone was valid
                if($return->name){
                    //Store weather data
                    $cond = $return->weather[0]->main;
                    $ext_cond = $return->weather[0]->description;
                    $iconcode = $return->weather[0]->icon;
                    $temp = $return->main->temp;
                    $country = $return->sys->country;
                    $city = $return->name;
                    $TempC = round(($temp - 32) * (5/9), 2);
                    
                    //Build icon URL
                    $icon = "http://openweathermap.org/img/w/{$iconcode}.png";
                    
                    //Build message
                    $message = "The current weather for {$city}, {$country} is: **{$ext_cond}** [{$temp}°F / {$TempC}°C]";
                    
                    //Build embed
                    $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':cloud:Weather',
                        'description' => "The current weather for {$city}, {$country} is: **{$ext_cond}**",
                        'timestamp' => false,
                        'fields' => [
                            ['name' => 'Temp °F', 'value' => "{$temp}", 'inline' => true],
                            ['name' => 'Temp °C', 'value' => "{$TempC}", 'inline' => true],
                        ],
                        'thumbnail' => $this->discord->factory(Discord\Parts\Embed\Image::class, [
                            'url' => $icon
                        ]),
                    ]);

                    //Send message to chat
                    send_embed_message($reply_to,$embed);
                }else{
                    //Build message
                    $message = "Error getting weather data.";
                    
                    //Send message
                    send_message($channelID,$fromID,$message);                
                }
            }else{
                //Build message
                $message = "No **city/zip** given for weather!";
            
                //Send message
                send_message($channelID,$fromID,$message);                    
            }
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "weather";
        $commands[0]["trigger"] = array("!weather");
        $commands[0]["information"] = "Gets the current weather for specified zip code, city etc.\r\n**Usage:** !weather *Boston*\r\n";
        $commands[0]["admin_command"] = 0;
        
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }
}
