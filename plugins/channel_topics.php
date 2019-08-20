<?php

class channel_topics{
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
        
        //Set timer to fire 10 seconds after bot is loaded (should be connected by then)
        $discord->loop->addTimer(10, function () use ($discord) {
            //Pull timers from database and set them up
            $timers = new channel_topics();
            $timers->load_timers();
        });
    }

    function __construct(){
        //Add globals for if called outside bot init
        global $f3, $discord, $logger;
        
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
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "addtopic";
        $commands[0]["trigger"] = array("!addtopic");
        $commands[0]["information"] = "Adds a channel topic. A channels name and description can be changed automatically at a given time, and an optional message sent in that channel.\r\n**Usage:** !addtopic t=**[time]**, c=**[channel id]**, n=**[new channel name]**, d=**[new description]**, m=**[message sent to channel]**\r\n";
        $commands[0]["admin_command"] = 1;
        $commands[1]["name"] = "listtopics";
        $commands[1]["trigger"] = array("!listtopics");
        $commands[1]["information"] = "Lists all channel topics for a server.\r\n**Usage:** !listtopics\r\n";
        $commands[1]["admin_command"] = 1;
        $commands[2]["name"] = "removetopic";
        $commands[2]["trigger"] = array("!removetopic");
        $commands[2]["information"] = "Removes a channel topic from the database.\r\n**Usage:** !removetopic *[channel ID]* *[topic ID]*\r\n";
        $commands[2]["admin_command"] = 1;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
        //Include database, cache and timers
        global $db, $cache, $loaded_timers;
        
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        $fromName = $msgData["message"]["from"];
        
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
        
        //Set the guild ID for server or PM
        if ($msgData["guild"]["id"] != null){
            $guildID = $msgData["guild"]["id"];
        }else{
            $guildID = null;
        }
        
        //Check if trigger was processed
        if(isset($command['command'])){
            //Check if message was sent from PM
            if($guildID == null){
                //This command cannot be used in PM
                return false;
            }
            
            //Check command
            switch ($command['command']){
                //Add channel topic
                case "addtopic":
                    //Run input through regex and split the values we are looking for
                    preg_match_all("/ \s*([^=]+) \s*=\s* ([^,]+) (?<!\s) /x", $msgarg, $split);
                    
                    //Combine split arrays from regex
                    $result = array_combine($split[1], $split[2]);
                    
                    //Remove unwanted junk from keys and combine into final array
                    $keys = str_replace( ', ', '', array_keys($result));
                    $results = array_combine($keys, array_values($result));
                    
                    //Check for time argument
                    if ($results['t'] != null){
                        //Convert given time to a unix timestamp
                        if (($time = strtotime($results['t'])) === false) {
                            //Time format could not be converted
                            $timestamp = false;
                        }else{
                            //Return unix timestamp
                            $timestamp = $time;
                        }
                    }else{
                        //Time argument not passed
                        $timestamp = false;
                    }

                    //Check for channel ID argument
                    if ($results['c'] != null){
                        //Check whether a channel ID was passed or a channel name
                        if(is_numeric($results['c'])){
                            //ID passed, check for valid channel ID
                            $channel_id = find_channel($results['c']);
                            
                            if($channel_id){
                                //Object is valid, get channel object
                                $channel_id = $channel_id->first();
                            }else{
                                //No channel found
                                $channel_id = false;
                            }
                        }else{
                            //Name passed, get guild object
                            $guild = $this->discord->guilds->get('id', $guildID);
                            
                            //Try to look up channel by name
                            $channel = $guild->channels->getAll('name', $results['c']);
                            
                            //Check if channel object was returned
                            if(count($channel) > 0){
                                //Object is valid, get channel object
                                $channel_id = $channel->first();
                            }else{
                                //No channel found
                                $channel_id = false;
                            }
                        }
                    }else{
                        //Channel ID argument not passed
                        $channel_id = false;
                    }

                    //Check for channel name argument
                    if ($results['n'] != null){
                        //Check if name is too short or too long
                        if(strlen($results['n']) < 2){
                            //Name too short
                            $channel_name = false;
                        }elseif(strlen($results['n']) > 50){
                            //Name too long, truncate
                            $channel_name = substr($results['n'], 0, 50);
                        }else{
                            //Set channel name
                            $channel_name = $results['n'];
                        }
                    }else{
                        //Channel name argument not passed
                        $channel_name = false;
                    }
                    
                    //Check for channel description argument
                    if ($results['d'] != null){
                        //Check if description is too long
                        if(strlen($results['d']) > 1000){
                            //Description too long, truncate
                            $channel_description = substr($results['d'], 0, 1000);
                        }else{
                            //Set channel description
                            $channel_description = $results['d'];
                        }                        
                    }else{
                        //Channel description argument not passed
                        $channel_description = false;
                    }

                    //Check for message argument
                    if ($results['m'] != null){
                        //Check if message is too long
                        if(strlen($results['m']) > 1000){
                            //Message too long, truncate
                            $msg = substr($results['m'], 0, 1000);
                        }else{
                            //Set message
                            $msg = $results['m'];
                        } 
                    }else{
                        //Message argument not passed
                        $msg = false;
                    }

                    //Check for necessary arguments
                    if($timestamp != false && $channel_id != false){
                        //Store the topic info
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':white_check_mark: Set topic',
                            'description' => "Your topic info was added to the database.",
                            'timestamp' => false,
                            'fields' => [
                                ['name' => 'Time', 'value' => "```c\r\n{$timestamp}\r\n```", 'inline' => true],
                                ['name' => 'Channel ID', 'value' => "```c\r\n{$channel_id->id}\r\n```", 'inline' => true],
                                ['name' => 'New Channel Name', 'value' => "```c\r\n{$channel_name}\r\n```", 'inline' => true],
                                ['name' => 'New Channel Description', 'value' => "```c\r\n{$channel_description}\r\n```", 'inline' => true],
                                ['name' => 'Message', 'value' => "```c\r\n{$msg}\r\n```", 'inline' => true],
                            ],
                        ]);
                        
                        //Create timer
                        $this->start_timer($guildID, $channel_id->id, $timestamp, $channel_name, $channel_description, $msg);
                    }else{
                        //No time or channel ID passed
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Set topic',
                            'description' => "A valid time and/or channel ID were not found. You need to at least give a time and channel ID for this command.",
                            'timestamp' => false,
                        ]);
                    }
                    
                    //Send message to chat
                    send_embed_message($reply_to,$embed);
                    break;
                
                //List all channel topics
                case "listtopics":
                    //Get topic list from database
                    try{
                        $timers = $db->exec('SELECT * FROM timers WHERE guild_id = ? AND bot_id = ?', array(1=>$guildID,2=>$this->f3->get("instance")));
                    }catch(Exception $e){
                        //Database error
                        $this->logger->err("List Timers - DB Error when getting timers: {$e->getMessage()}");
                        $timers = false;
                    }

                    //Check if there are any stored timers
                    if(count($timers) > 0){
                        //We got back at least one timer, loop through
                        foreach($timers as $timer){
                            //Transform timer data as needed
                            $channel = find_channel($timer['channel_id']);
                            
                            //Check for valid channel data
                            if($channel){
                                //Object is valid, get channel object and name
                                $channel = $channel->first();
                                $channel_name = $channel->name;
                            }else{
                                //No channel found
                                $channel_name = false;
                            }

                            //Convert unix timestamp to date
                            $time = date("Y-m-d H:i:s",$timer['expires_at']);

                            //Put timer data into embed
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ":timer: List topics - Timer {$timer['id']}",
                                'timestamp' => false,
                                'fields' => [
                                    ['name' => 'Run Time', 'value' => "```\r\n{$time}\r\n```", 'inline' => true],
                                    ['name' => 'Channel', 'value' => "```\r\n{$channel_name}\r\n```", 'inline' => true],
                                    ['name' => 'New Channel Name', 'value' => "```\r\n{$timer['new_channel_name']}\r\n```", 'inline' => true],
                                    ['name' => 'New Channel Description', 'value' => "```c\r\n{$timer['new_channel_desc']}\r\n```", 'inline' => true],
                                    ['name' => 'Message', 'value' => "```\r\n{$timer['message']}\r\n```", 'inline' => true],
                                ],
                            ]);
                            
                            //Send embed to channel
                            send_embed_message($reply_to,$embed);
                        }
                    }else{
                        //We didn't get any timers back, build embed message
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':timer: List topics',
                            'description' => "There are no stored topics currently active.",
                            'timestamp' => false,
                        ]);
                        
                        //Send embed to channel
                        send_embed_message($reply_to,$embed);                        
                    }
                    break;
                
                //Remove channel topic
                case "removetopic":
                    //Check for valid input
                    if($arguments[0] != null && is_numeric($arguments[0])){
                        //Input valid, store timer ID
                        $timer_id = $arguments[0];

                        //Query to make sure ID is valid
                        try{
                            $timers = $db->exec('SELECT * FROM timers WHERE id = ? AND guild_id = ? AND bot_id = ?', array(1=>$timer_id,2=>$guildID,3=>$this->f3->get("instance")));
                        }catch(Exception $e){
                            //Database error
                            $this->logger->err("Remove Timer - DB Error when getting timer ID {$timer_id}: {$e->getMessage()}");
                            $timers = false;
                        }
                        
                        if(count($timers) > 0){
                            //Timer ID is valid, remove from database
                            try{
                                $timer_remove = $db->exec('DELETE FROM timers WHERE id = ? AND bot_id = ?', array(1=>$timer_id,2=>$this->f3->get("instance")));
                                $this->logger->debug("Remove Timer - Removed timer ID {$timer_id} from database per request.");
                            }catch(Exception $e){
                                //Database error
                                $this->logger->err("Remove Timer - DB Error when removing timer ID {$timer_id}: {$e->getMessage()}");
                                $timer_remove = false;
                            }
                            
                            //Cancel running timer
                            try{
                                $this->discord->loop->cancelTimer($loaded_timers[$timer_id]);
                            }catch(Exception $e){
                                $this->logger->err("Remove Timer - Timer ID {$timer_id} could not be stopped: {$e->getMessage()}");
                            }
                            
                            //Check if removal was successful
                            if($timer_remove){
                                //Timer was removed successfully, setup embed message
                                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                    'title' => ':white_check_mark: Remove topic',
                                    'description' => "The specified timer with ID {$timer_id} was removed.",
                                    'timestamp' => false,
                                ]);
                            }else{
                                //Error removing timer, setup embed message
                                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                    'title' => ':x: Remove topic',
                                    'description' => "The specified timer with ID {$timer_id} could not be removed, either it has expired, or there was an error.",
                                    'timestamp' => false,
                                ]);
                            }
                        }else{
                            //Timer ID is not in database, setup embed message
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ':x: Remove topic',
                                'description' => "The specified channel ID is not valid.",
                                'timestamp' => false,
                            ]);
                        }
                    }else{
                        //Invalid input, setup embed message
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Remove topic',
                            'description' => "A valid channel ID was not found.",
                            'timestamp' => false,
                        ]);
                    }
                    
                    //Send embed to channel
                    send_embed_message($reply_to,$embed);                    
                    break;
            }
        }
    }
    
    function start_timer($guild_id, $channel_id, $expires_at, $new_channel_name, $new_channel_desc, $message){
        //Include database, cache and timers
        global $db, $cache, $loaded_timers;
        
        //Add channel info to database
        try{
            $insert = $db->exec('INSERT INTO timers (guild_id, channel_id, expires_at, new_channel_name, new_channel_desc, message, bot_id) VALUES(?, ?, ?, ?, ?, ?, ?)',array(1=>$guild_id,2=>$channel_id,3=>$expires_at,4=>$new_channel_name,5=>$new_channel_desc,6=>$message,7=>$this->f3->get("instance")));
            $timer_id = $db->lastInsertId();
        }catch(Exception $e){
            //Database error
            $this->logger->err("Create Timer - DB Error when adding timer: {$e->getMessage()}");
            return false;
        }
        
        //Calculate time in seconds timer will run
        $expire_time = ($expires_at - time());
        
        //Start timer
        $loaded_timers[$timer_id] = $this->discord->loop->addTimer($expire_time, function () use ($timer_id, $expire_time){
            $this->expire_timer($timer_id);
        });
        $this->logger->debug("Create Timer - Created timer ID {$timer_id} to expire in {$expire_time} seconds.");
    }
    
    function expire_timer($timer_id){
        //Include database and cache
        global $db, $cache;
        
        //Pull channel info from database
        try{
            $channel_info = $db->exec('SELECT id, guild_id, channel_id, expires_at, new_channel_name, new_channel_desc, message FROM timers WHERE id = ? AND bot_id = ?', array(1=>$timer_id,2=>$this->f3->get("instance")));
        }catch(Exception $e){
            //Database error
            $this->logger->err("Expire Timer - DB Error when getting timer info: {$e->getMessage()}");
            return false;
        }
        
        //Check if we got a timer back
        if(count($channel_info) == 0){
            //The timer ID passed was not valid, log and return false
            $this->logger->err("Expire Timer - Passed an invalid timer ID: {$timer_id}");
            return false;
        }
        
        //Get channel object from channel ID
        $channel = find_channel($channel_info[0]['channel_id']);
        $guild = $this->discord->guilds->get('id', $channel_info[0]['guild_id']);
        
        //Check if channel is valid
        if(!$channel){
            //The channel ID passed was not valid, log and return false
            $this->logger->err("Expire Timer - Passed an invalid channel ID: {$channel_info[0]['channel_id']}");
            return false;
        }else{
            //Get real channel object (first)
            $channel = $channel->first();
        }
        
        //Check if new channel name was specified
        if($channel_info[0]['new_channel_name'] != false){
            //Change channel name
            $channel->name = $channel_info[0]['new_channel_name'];
            
            //Save changes
            $channel_change1 = $guild->channels->save($channel);
            
            if(!$channel_change1){
                $this->logger->err("Expire Timer - Could not save channel name.");
            }else{
                $this->logger->debug("Expire Timer - Changed channel name: {$channel_info[0]['new_channel_name']}");
            }
        }
        
         //Check if new channel description was specified
        if($channel_info[0]['new_channel_desc'] != false){
            //Change channel description
            $channel->topic = $channel_info[0]['new_channel_desc'];
            
            //Save changes
            $channel_change2 = $guild->channels->save($channel);
            
            if(!$channel_change2){
                $this->logger->err("Expire Timer - Could not save channel description.");
            }else{
                $this->logger->debug("Expire Timer - Changed channel description: {$channel_info[0]['new_channel_desc']}");
            }
        }
        
        //Send message to channel if specified
        if($channel_info[0]['message'] != false){
            //Send message to channel
            send_message($channel_info[0]['channel_id'],null,$channel_info[0]['message']);
            $this->logger->debug("Expire Timer - Sent message to channel: {$channel_info[0]['message']}");
        }
        
        //Remove timer from database
        try{
            $timers = $db->exec('DELETE FROM timers WHERE id = ? AND bot_id = ?', array(1=>$timer_id,2=>$this->f3->get("instance")));
            $this->logger->debug("Expire Timer - Removed timer ID {$timer_id} from database.");
        }catch(Exception $e){
            //Database error
            $this->logger->err("Expire Timer - DB Error when removing timer: {$e->getMessage()}");
            return false;
        }
        
        //Success!
        $this->logger->debug("Expire Timer - Timer ID {$timer_id} expired.");
        return true;
    }

    function load_timers(){
        //Include database, cache and timers
        global $db, $cache, $loaded_timers;
        
        //Create array for timers
        $loaded_timers = array();
        
        //Load all timers from database
        try{
            $timers = $db->exec('SELECT * FROM timers WHERE bot_id = ?', array(1=>$this->f3->get("instance")));
        }catch(Exception $e){
            //Database error
            $this->logger->err("Load Timers - DB Error when getting timers: {$e->getMessage()}");
            return false;
        }
        
        //Check if there are any stored timers
        if(count($timers) > 0){
            //There is at least one timer stored, loop through
            foreach($timers as $timer){
                //Calculate time in seconds timer will run
                $expire_time = ($timer['expires_at'] - time());
                $timer_id = $timer['id'];
                
                //Attempt to load timer
                try{
                    $loaded_timers[$timer['id']] = $this->discord->loop->addTimer($expire_time, function () use ($timer_id, $expire_time){
                        $this->expire_timer($timer_id);
                    });
                    $this->logger->debug("Load Timer - Created timer ID {$timer['id']} to expire in {$expire_time} seconds.");
                }catch(Exception $e){
                    //Database error
                    $this->logger->err("Load Timers - Error creating timer: {$e->getMessage()}");
                }
            }
        }else{
            //There are no stored timers, no need to go further
            $this->logger->debug("Load Timers - No timers loaded from database.");
            return false;
        }
    }
}