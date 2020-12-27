<?php

class help{
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
        
        //Set the ID for replies
        if ($msgData["guild"]["id"] !== null){
            $reply_to = $channelID;
        }else{
            $reply_to = $fromID;
        }
        
        //Process trigger and resolve command/argument parts
        $command = processTrigger($message, get_plugin_commands($this));
        $arguments = $command['arguments'];
        $msgarg = $command['argument'];
        
        //Check if trigger was processed
        if(isset($command['command'])){
            //Include plugins
            global $plugins;

            //Check if passed argument or not
            if (!$msgarg){
                //Show modules available
                $commands = array();
                foreach ($plugins as $plugin) {
                    $infoarr = $plugin->information();
                    //Check if array is valid
                    if(is_countable($infoarr) && count($infoarr) !== 0){
                        //Loop through commands
                        foreach($infoarr as $info){
                            //Check access level for command
                            if ($info["admin_command"] == 1){
                                //Check if user has access to run this command
                                if(is_admin($fromID)){
                                    //Add command into array
                                    $commands[] = $info["name"];
                                }
                            }else{
                                //Add command into array
                                $commands[] = $info["name"];
                            }
                        }
                    }
                }

                //Build embed
                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                    'title' => ':question: Help',
                    'description' => "No specific plugin requested, listing all available plugins available to you.",
                    'timestamp' => false,
                    'fields' => [
                        ['name' => 'Commands', 'value' => "**".implode("**  **", $commands)."**", 'inline' => true],
                    ],
                ]);

                //Send message to chat
                send_embed_message($reply_to,$embed);
            }else{
                //Argument passed, lookup command
                foreach ($plugins as $plugin){
                    $info = $plugin->information();
                    //Check if array is valid
                    if(is_countable($info) && count($info) !== 0){
                        //Loop through commands
                        foreach($info as $cmd){
                            //Check for command
                            if ($msgarg == $cmd["name"]){
                                //Build embed
                                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                    'title' => ":question: Help for command: {$cmd["name"]}",
                                    'description' => "{$cmd["information"]}",
                                    'timestamp' => false,
                                ]);
                
                                //Send message to chat
                                send_embed_message($reply_to,$embed);
                            }                        
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "help";
        $commands[0]["trigger"] = array("!help");
        $commands[0]["information"] = "Shows help for a plugin, or all the plugins available.\r\n**Usage:** !help *[command]*\r\n**Note:** If given no command, gives all available commands.";
        $commands[0]["admin_command"] = 0;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
