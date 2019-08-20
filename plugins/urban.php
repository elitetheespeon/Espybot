<?php

class urban{
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
            if(!$msgarg){
                //Build message
                $message = "Specify search term for command **".$command['command']."**";;
                
                //Send message
                send_message($channelID,$fromID,$message);                
            }else{
                //Make API call
                $client = new GuzzleHttp\Client();
                $body = $client->get('http://api.urbandictionary.com/v0/define?term='.$msgarg)->getBody();
                $return = json_decode($body);
                
                //Check if respone was valid
                if($return){
                    //Store definitions
                    $defs = $return->list;
                    
                    //Make array of definitions entries
                    $defarr = array();
                    
                    //Sort through definitions array
                    foreach($defs as $def){
                        $defarr[] = $def->definition;
                    }
                    
                    $max_rand = count($defarr);
                    
                    //Check for valid entry, loop through until get one
                    $valid = false;
                    $try = 0;
                    while($valid == false && $try < 10){
                        //Increase attempt number
                        $try++;
                        
                        //Grab a random entry
                        $rand_def = rand(0,$max_rand-1);
                        $definition = $defarr[$rand_def];
                        $link = $defs[$rand_def]->permalink;
                        
                        if(trim($definition) !== ""){
                            $valid = true;
                        }
                    }
                    
                    //If we still got nothing by this point, let the user know
                    if(!$definition){
                        //Build embed
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':globe_with_meridians: Urban Dictionary',
                            'description' => "Your search for {$msgarg} was not found on Urban Dictionary, try another search term.",
                            'timestamp' => false,
                        ]);
                    }else{
                        //Check if definition is over 500 characters and truncate
                        if(strlen($definition) > 500){
                            $definition = substr($definition, 0, 500)."...";
                        }

                        //Build embed
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ":globe_with_meridians: Urban Dictionary definition: {$msgarg}",
                            'url' => $link,
                            'description' => "{$definition}",
                            'timestamp' => false,
                        ]);
                    }
                }else{
                    //Build embed
                    $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':globe_with_meridians: Urban Dictionary',
                        'description' => "There was an error connecting to Urban Dictionary, try again later.",
                        'timestamp' => false,
                    ]);
                }
                
                //Send message to chat
                send_embed_message($reply_to,$embed);
            }
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "urban";
        $commands[0]["trigger"] = array("!urban");
        $commands[0]["information"] = "Grabs the first word from Urban Dictionary for the search term.\r\n**Usage:** !urban *Magnus*\r\n";
        $commands[0]["admin_command"] = 0;
        
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }
}
