<?php

class gameintegration{
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
        $guildID = $msgData["guild"]["id"];
        
        //Process trigger and resolve command/argument parts
        $command = processTrigger($message, get_plugin_commands($this));
        
        //Check if trigger was processed
        if(isset($command['command'])){
            //Check command
            switch ($command['command']){
                //List game servers
                case "servers":
                    $msgcontent = "Server List: \r\n-------------------------\r\n";
                    
                    //Check there are servers
                    if(count($this->f3->get('gameserver_ips')) !== 0){
                        //Loop through servers
                        foreach($this->f3->get('gameserver_ips') as $server){
                            $msgcontent .= $server['name']."\r\n";
                            $msgcontent .= "IP: ".$server['ip'].":".$server['port']."\r\n\r\n";
                        }
                    }
                    
                    //Send message
                    send_message($channelID,$fromID,$msgcontent, true);                        
                    break;
            }
        }
    }
    

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "servers";
        $commands[0]["trigger"] = array("!servers");
        $commands[0]["information"] = "List out the current server list.\r\n";
        $commands[0]["admin_command"] = 0;

        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
