<?php

class chatlog_notify{
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
        
        //Check if empty message or attachement
        if(is_countable($attachments) && count($attachments) > 0  && $message == ""){
            //Ignore message as there is no text
            return;
        }else{
            //Get current notifications
            $notifications = get_notifications();
            //Check if there are any notifications
            if($notifications){
                //Loop through notifications
                foreach($notifications as $notification){
                    $foundmatch = false;
                    //Check if guild id matches
                    if($guildID == $notification['value1']){
                        //Convert notification words
                        $searcharr = json_decode($notification['value3']);
                        //Check if notification words are valid
                        if($searcharr){
                            //Loop through notification words
                            foreach($searcharr as $search){
                                //Check for a match
                                if(strpos($message,$search) !== false){
                                    //Hilight found word(s)
                                    $message = preg_replace('/('.$search.')/i',"**$search**",$message);
                                    $foundmatch = true;
                                    
                                }
                            }
                        }
                    }
                    //Check if matches were found
                    if($foundmatch){
                        //Match was found, send notification to user
                        if($notification['value2']){
                            //Build message
                            $msgcontent = "📰Notification [".date("m/d/y - h:i:s A",date("U",strtotime($timestamp)))."] [".$guildName."] [".$channelName."] ".$fromName." - ".$message;
                            //Send message to user
                            $user_id = $notification['value2'];
                            send_message($user_id,$user_id,$msgcontent);
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
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
