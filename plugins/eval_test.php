<?php

class eval_test{
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
        
        //Process trigger and resolve command/argument parts
        $command = processTrigger($message, get_plugin_commands($this));
        $arguments = $command['arguments'];
        $msgarg = $command['argument'];
        
        //Check if trigger was processed
        if(isset($command['command']) && is_bot_owner($fromID)){
            //Eval statement
            try{
                $return = eval($msgarg);
                ob_start();
                var_dump($test);
                $content = ob_get_contents();
                ob_end_clean();
                send_message($channelID,$fromID,$content,true,'php');
            }catch(Exception $e){
                $content = "Error parsing: ".$e->getMessage();
                send_message($channelID,$fromID,$content,true,'php');
            }
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "eval";
        $commands[0]["trigger"] = array("!eval");
        $commands[0]["information"] = "Only can be used by bot owner, if you don't know PHP then this is not for you.\r\n";
        $commands[0]["admin_command"] = 1;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
