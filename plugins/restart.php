<?php

class restart{
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
        
        //Check if trigger was processed
        if(isset($command['command']) && is_bot_owner($fromID)){
            //Build embed
            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                'title' => ':bomb: Restart',
                'description' => "Restart command given, restarting...",
                'timestamp' => false,
            ]);

            //Send message to chat
            send_embed_message($reply_to,$embed);
            
            //Kill bot
            $this->logger->err("Restart command given - restarting...");
            exit();
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "restart";
        $commands[0]["trigger"] = array("!restart");
        $commands[0]["information"] = "Can **only** be used by bot owner! - Restarts bot.\r\n";
        $commands[0]["admin_command"] = 1;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
