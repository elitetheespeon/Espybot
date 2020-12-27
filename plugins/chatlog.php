<?php

class chatlog{
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

    function onMessage(){
    }

    /**
     * @param $msgData
     */
    function onLog($msgData){
        //Include database
        global $db;
        
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $guildID = $msgData["guild"]["id"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        $fromName = $msgData["message"]["from"];
        $timestamp = $msgData["message"]["timestamp"];
        $attachments = $msgData["message"]["attachments"];
        $format_time = date("m/d/y - h:i:s A",date("U",strtotime($timestamp)));
        
        //Check for ignored user ids
        if(is_countable($this->f3->get('ignore_chat_log')) && count($this->f3->get('ignore_chat_log')) > 0){
            //Loop through user ignore list
            foreach($this->f3->get('ignore_chat_log') as $ignored_id){
                //Don't log anything from ignored user id
                if($fromID == $ignored_id){
                    //Get out of here
                    return;
                }
            }
        }
        
        //Check message
        if(is_countable($attachments) && count($attachments) > 0  && $message == ""){
            //Message with attachment
            $this->logger->info("Attachment(s) recieved - [{$guildName}] [#{$channelName}] {$fromName}");
        }elseif($message == ""){
            //Emtpy message
            $this->logger->info("Empty message recieved - [{$guildName}] [#{$channelName}] {$fromName}");
        }else{
            //Normal chat message
            try{
                //Log chat message to database
                $insert=new DB\SQL\Mapper($db,'logs');
                $insert->date=date("Y-m-d H:i:s",date("U",strtotime($timestamp)));
                $insert->guild = $guildID;
                $insert->message = $message;
                $insert->channel = $channelID;
                $insert->user = $fromID;
                $insert->bot_id = $this->f3->get("instance");
                $insert->save();
            }catch(Exception $e){
                //Error logging to database
                $this->logger->err("Error logging chat message to database!");
            }
            
            //Log chat message to console
            $this->logger->message("[{$format_time}] [{$guildName}] [#{$channelName}] {$fromName} - {$message}");
            
            try{
                //Log chat message to file
                $logfile = file_put_contents("logs/{$guildName}.txt", "[{$format_time}] [{$guildName}] [#{$channelName}] {$fromName} - {$message}".PHP_EOL , FILE_APPEND);
            }catch(Exception $e){
                //Error writing to log
                $this->logger->err("Error writing to log file {$guildName}.txt!");
            }
        }
        
        //Update last seen
        if($fromID !== null){
            try{
                //Insert into database for last seen
                $insert = $db->exec('INSERT INTO last_seen (user_id,last_spoke,bot_id) VALUES(?,NOW(),?) ON DUPLICATE KEY UPDATE last_spoke = NOW()',array(1=>$fromID,2=>$this->f3->get("instance")));
            }catch(Exception $e){
                //Couldn't log chat message to database
                $this->logger->err("Error updating last seen for user ID {$fromID}!");
            }
        }

    }

    /**
     * @return array
     */
    function information(){
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
