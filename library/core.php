<?php
function processTrigger($message, $commands){
    //Include F3
    global $f3;
    
    //Check if commands are defined
    if(empty($commands)){
        //No command(s) given
        return false;
    }
    
    //Check if message is defined
    if(!$message){
        //No message given
        return false;
    }

    //Check if command is array or single command (if single command, make it array)
    $commands = is_array($commands) ? $commands : array($commands);

    //Loop through each command
    foreach($commands as $command) {
        //Check if command matches valid command
        if(substr($message, 0, strlen($command)) == $command) {
            //Break up message into arguments
            $data = explode(" ", $message);
            
            //Remove trigger from command
            $command = str_replace($f3->get('trigger'), '', $data[0]);
            unset($data[0]);
            
            //Rejoin message for argument list as string
            $data = array_values($data);
            $messageString = implode(" ", $data);

            //Send back command data
            return array("command" => $command, "arguments" => $data, "argument" => $messageString);
        }
    }
    
    //No matching command found
    return false;
}

function containsTrigger($message, $commands){
    //Check if commands are defined
    if(empty($commands)){
        //No command(s) given
        return false;
    }
    
    //Check if message is defined
    if(!$message){
        //No message given
        return false;
    }

    //Check if command is array or single command (if single command, make it array)
    $commands = is_array($commands) ? $commands : array($commands);

    //Loop through each command
    foreach($commands as $command) {
        //Check if command matches valid command
        if(substr($message, 0, strlen($command)) == $command)
            //Valid command was found
            return substr($command, 1);
    }
    
    //No matching command found
    return false;
}

function get_plugin_commands($plugin){
    //Include F3
    global $f3;

    //Set defaults
    $commands = array();

    //Call plugin information function
    $infoarr = $plugin->information();
    
    //Check if plugin information was returned
    if(is_countable($infoarr) && count($infoarr) !== 0){
        //Loop through each command
        foreach($infoarr as $info){
            //Check if command name is valid
            if ($info["name"]){
                //Store command in array, inserting trigger
                $commands[] = $f3->get('trigger').$info["name"];
            }
        }
    }
    
    //Send array of commands back
    return $commands;
}

function call_event($type,$data){
    global $discord,$logger,$f3,$db;
    
    switch ($type){
        //Called when user joins guild
        case 'GUILD_MEMBER_ADD':
            //Check if event is enabled
            if ($f3->get('notify_user_join') === false){
                return false;
            }
            
            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
            
            //Save out data to variables
            $j_username = $data->user->username;
            $j_userid = $data->user->id;
            $j_desc = $data->user->discriminator;
            $j_avatar = $data->user->avatar;
            $joined_at = $data->joined_at;
            $joined_at_f = date("Y-m-d H:i:s",date("U",strtotime($joined_at)));
            $mute = $data->mute;
            $deaf = $data->deaf;
            $mute_c = ($data->mute) ? 'true' : 'false';
            $deaf_c = ($data->deaf) ? 'true' : 'false';
            
            //Build message
            $message = "User ".$j_username."#".$j_desc." has joined chat [ID:".$j_userid."]";
    
            //Get common guilds from OH
            if($f3->get('oh_enable')){
                //Check if NF
                if($data->guild_id == '90604750097113088'){
                    //Pull common servers
                    $servers = oh_common_servers($j_userid);
                    //Check if we got data back
                    if($servers){
                        $ohbit = implode(", ",$servers);
                        $ohdata = "\r\n **Servers:** {$ohbit}";
                    }
                }
            }
            
            //Build embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**User joined**")
                ->setDescription("**Name:** {$j_username}#{$j_desc} \r\n **ID:** {$j_userid} {$ohdata}")
                ->setColor(3532089)
                ->setTimestamp()
                ->setImage($j_avatar);
    
            //Send notification
            send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            
            //Get users to notify
            $notify_users = get_notification_users($data->guild->id);
            
            //Check for users to notify
            if(is_array($notify_users)){
                //Got back list of users to notify, loop through
                foreach($notify_users as $notify_user){
                    //Send a notification to each user
                    send_notification(null,$notify_user,null,$embed,$data->guild->id);
                }
            }
            
            //Apply group specific roles
            if(!empty($f3->get('set_role_new_members')) && is_countable($f3->get('set_role_new_members')) && count($f3->get('set_role_new_members')) > 0){
                //Loop through each guild
                foreach($f3->get('set_role_new_members') as $guild){
                    //Check if guild exists in config
                    if($guild['guild'] == $data->guild->id){
                        $logger->notice("SET ROLE: New user in ".find_guild($data->guild->id)->name." -> Adding new member role.");
                        
                        //Get member object from ID
                        $guildobj = $discord->guilds->get('id',$data->guild->id);
                        $member = $guildobj->members->get('id',$j_userid);
                        
                        //Add role to member
                        if($member->addRole($guild['role'])) {
                             $guildobj->members->save($member)->then(function () use ($guildobj, $logger, $member){
                                  $logger->notice("SET ROLE: Successfully added role for {$member->username} in {$guildobj->name}.");
                             }, function ($e) use ($guildobj, $logger, $member){
                                  $logger->warn("SET ROLE: Error adding role for {$member->username} in {$guildobj->name}: {$e->getMessage()}");
                             });
                        }else{
                             //Member already has the role
                             $logger->warn("SET ROLE: Skipped adding role for {$member->username} in {$guildobj->name}, user already has role.");
                        }
                    }
                }
            }
    
            //Update invites to see what invite code was used
            update_invites();
            break;
    
        //Called when a user leaves guild (also can be banned)
        case 'GUILD_MEMBER_REMOVE':
            //Check if event is enabled
            if ($f3->get('notify_user_leave') === false){
                return false;
            }
    
            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
            
            //Save out data to variables
            $j_username = $data->user->username;
            $j_userid = $data->user->id;
            $j_desc = $data->user->discriminator;
            
            //Build message
            $message = "User ".$j_username."#".$j_desc." has left chat [ID:".$j_userid."]";
    
            //Build embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**User left**")
                ->setDescription("**Name:** {$j_username}#{$j_desc} | **ID:** {$j_userid}")
                ->setColor(16711722)
                ->setTimestamp();
            
            //Send notification
            send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            
            //Get users to notify
            $notify_users = get_notification_users($data->guild->id);
            
            //Check for users to notify
            if(is_array($notify_users)){
                //Got back list of users to notify, loop through
                foreach($notify_users as $notify_user){
                    //Send a notification to each user
                    send_notification(null,$notify_user,null,$embed,$data->guild->id);
                }
            }
            break;
            
        //Called when a user is banned from guild
        case 'GUILD_BAN_ADD':
            //Check if event is enabled
            if ($f3->get('notify_ban') === false){
                return false;
            }
                
            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
            
            //Save out data to variables
            $j_username = $data->user->username;
            $j_userid = $data->user->id;
            $j_desc = $data->user->discriminator;
            $j_reason = $data->reason;
            
            //Build reason message
            if($j_reason){
                $reason = "for reason *".$j_reason."* ";
            }
            
            //Build message
            $message = "User ".$j_username."#".$j_desc." was banned from chat ".$reason."[ID:".$j_userid."]";
    
            //Build embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**User banned**")
                ->setDescription("**Name:** {$j_username}#{$j_desc} | **ID:** {$j_userid}")
                ->setColor(16711722)
                ->setTimestamp();
            
            //Send notification
            send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            
            //Get users to notify
            $notify_users = get_notification_users($data->guild->id);
            
            //Check for users to notify
            if(is_array($notify_users)){
                //Got back list of users to notify, loop through
                foreach($notify_users as $notify_user){
                    //Send a notification to each user
                    send_notification(null,$notify_user,null,$embed,$data->guild->id);
                }
            }
            break;
            
        //Called when a user is unbanned from guild
        case 'GUILD_BAN_REMOVE':
            //Check if event is enabled
            if ($f3->get('notify_ban_removed') === false){
                return false;
            }
            
            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
    
            //Save out data to variables
            $j_username = $data->user->username;
            $j_userid = $data->user->id;
            $j_desc = $data->user->discriminator;
            
            //Build message
            $message = "User ".$j_username."#".$j_desc." was unbanned from chat [ID:".$j_userid."]";
            
            //Build embed message
            $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**User unbanned**")
                ->setDescription("**Name:** {$j_username}#{$j_desc} | **ID:** {$j_userid}")
                ->setColor(16763904)
                ->setTimestamp();
            
            //Send notification
            send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            
            //Get users to notify
            $notify_users = get_notification_users($data->guild->id);
            
            //Check for users to notify
            if(is_array($notify_users)){
                //Got back list of users to notify, loop through
                foreach($notify_users as $notify_user){
                    //Send a notification to each user
                    send_notification(null,$notify_user,null,$embed,$data->guild->id);
                }
            }
            break;
            
        //Called when a user updates presence
        case 'PRESENCE_UPDATE':
            //Check if event is enabled
            if ($f3->get('notify_user_presence') === false){
                return false;
            }
            
            //Save out data to variables
            $j_username = $data->user->username;
            $j_userid = $data->user->id;
    
            //Check if status update
            if($data->status){
                try{
                    //Update last online
                    $insert = $db->exec('INSERT INTO last_seen (user_id,last_online,bot_id) VALUES(?,NOW(),?) ON DUPLICATE KEY UPDATE last_online = NOW()',array(1=>$j_userid,2=>$f3->get('instance')));
                }catch(Exception $e){
                    //DB call failed
                    $logger->err("LASTSEEN - Error updating last online for user ID ".$j_userid."!");
                }
            }
    
            //Check if username update       
            if($j_username){
                try{
                    //Add username into name history
                    $insert = $db->exec('INSERT INTO name_history (user_id,user_name,bot_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE date = NOW()',array(1=>$j_userid,2=>$j_username,3=>$f3->get('instance')));
                }catch(Exception $e){
                    //DB call failed
                    $logger->err("NAMEHIST - Error updating name history for user ID ".$j_userid."!");
                }
            }
            return true;
            break;
            
        //Called when a channel is created
        case 'CHANNEL_CREATE':
            //Check if event is enabled
            if ($f3->get('notify_channel_add') === false){
                return false;
            }

            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
            
            //Check if private channel
            if(property_exists($data, 'type') && $data->type != 1){
                //Save out data to variables
                $channel_id = $data->id;
                $channel_name = $data->name;
                $channel_position = $data->position;
                $channel_type = $data->type;
                
                //Get channel type
                $channel_type = get_channel_type($channel_type);
                
                //Build message
                $message = $channel_type." channel #".$channel_name." created [ID:".$channel_id."]";
    
                //Build embed message
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**{$channel_type} channel created**")
                ->setDescription("**Name:** #{$channel_name} | **ID:** {$channel_id}")
                ->setColor(3532089)
                ->setTimestamp();
                
                //Send notification
                send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            }
            break;
                
        //Called when a channel is deleted
        case 'CHANNEL_DELETE':
            //Check if event is enabled
            if ($f3->get('notify_channel_delete') === false){
                return false;
            }
            
            //Check which guild this came from and determine admin channel
            $admin_chan = get_adminchan($data->guild->id);
            
            //Check if private channel
            if(!property_exists($data, 'is_private')){
                //Save out data to variables
                $channel_id = $data->id;
                $channel_name = $data->name;
                $channel_position = $data->position;
                $channel_type = $data->type;
                
                //Get channel type
                $channel_type = get_channel_type($channel_type);
                
                //Build message
                $message = $channel_type." channel #".$channel_name." deleted [ID:".$channel_id."]";
                
                //Build embed message
                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                ->setTitle("**{$channel_type} channel deleted**")
                ->setDescription("**Name:** #{$channel_name} | **ID:** {$channel_id}")
                ->setColor(16711722)
                ->setTimestamp();
                
                //Send notification
                send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$data->guild->id);
            }
            break;

        //Called when a message is changed
        case 'MESSAGE_UPDATE':
            //We currently do not use this event and it fires on embed messages
            return false;
            break;

        //Called when a user join/switches/leaves voice channel
        case 'VOICE_STATE_UPDATE':
            //We currently do not use this event and it fires on embed messages
            return false;
            break;
            
        //Unhandled event
        default:
            //Log unhandled event
            $logger->debug("CALL EVENT: Got event {$type}, no handler exists for this event.");
            //dump($data);
            break;
    }
}

function send_notification($channelID,$userID,$message,$data,$guild_id){
    //Pull in globals
    global $discord,$logger,$f3,$db;
    
    //Check if channel ID is array
    if(is_array($channelID)){
        //Loop through each channel ID
        foreach($channelID as $channel){
            //Run each user ID through this function
            send_notification($channel,null,$message,$data,$guild_id);
            
            //Null message to avoid repeat messages
            $message = null;
        }
        //Get out of this loop
        return;
    }

    //Check if user ID is array
    if(is_array($userID)){
        //Loop through each user ID
        foreach($userID as $user){
            //Run each user ID through this function
            send_notification(null,$user,$message,$data,$guild_id);
            
            //Null message to avoid repeat messages
            $message = null;
        }
        //Get out of this loop
        return;
    }

    //Check if admin channel was given
    if(find_channel($channelID)){
        //Send message to admin channel
//eval(\Psy\sh());
//dump($channelID);
//dump($data);
        send_embed_message($channelID,$data);
    }else{
        //Get guild
        $guild = find_guild($guild_id);
        
        //Check if guild name was already added
        if(strpos($data->title, $guild->name) === false) {
            //Set title of embed to guild name
            $data->title = "{$guild->name}: {$data->title}";
        }
        
        //Send message to bot admin
        send_embed_message($userID,$data);
    }
    
    //Check for message
    if($message){
        //Log message
        $logger->info($message);
    }
}

function is_admin($userid){
    //Pull in globals
    global $discord,$logger,$f3,$db;
    
    //Start loop to check userid against admin list
    if(!empty($f3->get('admins'))){
        foreach ($f3->get('admins') as $admin){
            //Check if ID matches
            if($userid == $admin["value1"]){
                //Userid is in admin list
                return true;
            }
        }
    }else{
        //Admin cache is empty, pull in from DB
        $logger->info("Admin cache empty, populating from DB.");
        $admins = $db->exec("SELECT `key`, `value1`, `value2`, `value3` FROM config WHERE `key` = 'admins' AND bot_id = ?",array(1=>$f3->get('instance')));
        if (is_countable($admins) && count($admins) !== 0){
            $f3->set('admins',$admins);
            foreach ($f3->get('admins') as $admin){
                //Check if ID matches
                if($userid == $admin["value1"]){
                    //Userid is in admin list
                    return true;
                }
            }            
        }else{
            $logger->info("No admins found in database.");
            return false;
        }
    }
    //If we didn't return true, userid is not in admin list
    return false;
}

function is_bot_owner($user_id){
    global $f3;

    //Check if bot owner has been defined
    if(!empty($f3->get('bot_owners')) && is_countable($f3->get('bot_owners')) && count($f3->get('bot_owners')) > 0){
        //Loop through user id
        foreach($f3->get('bot_owners') as $admin_id){
            //Check if given user id is a bot owner
            if($user_id == $admin_id){
                //User id is bot owner
                return true;
            }
        }
        
        //If we didn't return true, userid is not in admin list
        return false;
    }
}

function find_channel($channel_id){
    //Pull in globals
    global $discord;
    
    //Get guild list
    $guilds = $discord->guilds;

    //Check if guild list is empty
    if(is_countable($guilds) && count($guilds) > 0){
        //Loop through guilds
        foreach ($guilds as $guild){
            //Get channel
            
            $channel = $guild->channels->offsetGet($channel_id);
            
            //Check if channel object was returned
            if($channel->name){
                return $channel;
            }
        }
    }
    
    //Nothing found
    return false;
}

function find_channel_name($channel_name){
    //Pull in globals
    global $discord;
    
    //Get guild list
    $guilds = $discord->guilds;   

    //Check if guild list is empty
    if(is_countable($guilds) && count($guilds) > 0){
        //Loop through guilds
        foreach ($guilds as $guild){
            //Get channel
            $channel = $guild->channels->getAll('name',$channel_id);
            //Check if channel object was returned
            if(is_countable($channel) && count($channel) > 0){
                return $channel;
            }
        }
    }
    //Nothing found
    return false;
}

function find_member($user_id){
    //Pull in globals
    global $discord;
    
    //Get guild list
    $guilds = $discord->guilds;

    //Check if guild list is empty
    if(is_countable($guilds) && count($guilds) > 0){
        //Loop through guilds
        foreach ($guilds as $guild){
            //Get member
            $member = $guild->members->get('id',$user_id);
            //Check if member object was returned
            if(is_countable($member) && count($member) > 0){
                return $member;
            }
        }
    }
    //Nothing found
    return false;
}

function find_guild($guild_id){
    //Pull in globals
    global $discord;
    
    //Get guild list
    $guild = $discord->guilds->get('id', $guild_id);

    //Check if guild object was returned
    if(is_countable($guild) && count($guild) > 0){
        return $guild;
    }
    //Nothing found
    return false;
}

function get_all_member_guilds($user_id){
    //Pull in globals
    global $discord;
    $userguilds = array();
    
    //Get guild list
    $guilds = $discord->guilds;

    //Check if guild list is empty
    if(is_countable($guilds) && count($guilds) > 0){
        //Loop through guilds
        foreach ($guilds as $guild){
            //Get member
            $member = $guild->members->get('id',$user_id);
            //Check if member object was returned
            if(is_countable($member) && count($member) > 0){
                $userguilds[$member->guild_id] = $member;
            }
        }
    }
    
    //Check if user guild list is empty
    if(empty($userguilds)){
        return false;
    }else{
        return $userguilds;
    }
}

function get_adminchan($guild_id){
    //Pull in globals
    global $discord,$logger,$f3,$cache,$db;
    $channel_id = array();

    //Check if admin channel is set for guild
    if($cache->search($guild_id,'adminchan')){
        //Admin channel is set, loop through until we get the channel data
        foreach($f3->get('adminchan') as $key => $data){
            //Check for match and set
            if($data['value1'] == $guild_id){
                //Got the channel ID
                $channel_id[] = $data['value2'];
            }
        }
    }else{
        //No admin channel set
        $channel_id = false;
    }

    //Check if channel was found
    if ($channel_id){
        //Send channel id
        return $channel_id;
    }else{
        //We got nothing
        return false;
    }
}

function get_notification_users($guild_id){
    //Pull in globals
    global $discord,$logger,$f3,$cache,$db;
    $user_id = array();

    //Check if notify users is set for guild
    if($cache->search($guild_id,'notifyme')){
        //Notify users is set, loop through until we get the user data
        foreach($f3->get('notifyme') as $key => $data){
            //Check for match and set
            if($data['value1'] == $guild_id){
                //Got the user ID
                $user_id[] = $data['value2'];
            }
        }
    }else{
        //No notify users set
        $user_id = false;
    }

    //Check if users were found
    if ($user_id){
        //Send user id(s)
        return $user_id;
    }else{
        //We got nothing
        return false;
    }
}

function get_notifications(){
    //Pull in globals
    global $discord,$logger,$f3,$db,$cache;
    
    //Check if notifications have been cached
    if(!empty($f3->get('notifications'))){
        //Send back cached data
        return $f3->get('notifications');
    }else{
        //Notifications cache is empty, sync
        $logger->info("Notifications cache empty, populating from DB.");
        if($cache->sync('notifications')){
            //Send back cached data
            return $f3->get('notifications');       
        }else{
            //No notifications stored
            $logger->debug("No notifications found in database.");
            return false;
        }
    }
}

function get_last_seen($userid){
    //Pull in globals
    global $discord,$logger,$f3,$db;
    try{
        //Get user last seen time
        $lastseendb = $db->exec('SELECT user_id,user_name,last_online,last_spoke FROM espybot.last_seen WHERE user_id = ? AND bot_id = ? LIMIT 1',array(1=>$userid,2=>$f3->get('instance')));
        if(is_countable($lastseendb) && count($lastseendb) !== 0){
            //Pack data
            $return = array(
                'user_id' => $lastseendb[0]['user_id'],
                'user_name' => $lastseendb[0]['user_name'],
                'last_online' => $lastseendb[0]['last_online'],
                'last_spoke' => $lastseendb[0]['last_spoke']
            );
            return $return;
        }else{
            //User id not in db
            return false;
        }
    }catch(Exception $e){
        //Database error
        $logger->err("LASTSEEN - Error during checking last seen.");
        return false;
    }
}

function get_name_history($userid){
    //Pull in globals
    global $discord,$logger,$f3,$db;
    try{
        //Get user last seen time
        $namehistory = $db->exec('SELECT user_name FROM name_history WHERE user_id = ? AND bot_id = ?',array(1=>$userid,2=>$f3->get('instance')));
        if(is_countable($namehistory) && count($namehistory) !== 0){
            $return = "";
            //Pack data
            $i = 0;
            foreach($namehistory as $name){
                $i++;
                if(is_countable($namehistory) && $i == count($namehistory)){
                    $return .= $name['user_name']." ";
                }else{
                    $return .= $name['user_name']." | ";
                }
            }
            //Send name history
            return $return;
        }else{
            //User id not in db
            return false;
        }
    }catch(Exception $e){
        //Database error
        $logger->err("NAMEHIST - Error during checking name history.");
        return false;
    }
}

function get_channel_type($type){
    //Get channel type
    switch ($type) {
        case 0:
            $name = "Text";
            break;

        case 1:
            $name = "DM";
            break;
        
        case 2:
            $name = "Voice";
            break;
        
        case 4:
            $name = "Category";
            break;
        
        default:
            $name = $type;
            break;
    }
    
    //Return name
    return $name;
}

function send_embed_message($channel_id,$embed,$message = ''){
    //Pull in globals
    global $discord,$logger;

    //Check if channel ID is array
    if(is_array($channel_id)){
        //Loop through each channel ID
        foreach($channel_id as $channel){
            //Run each channel ID through this function
            send_embed_message($channel,$embed);
        }
        //Get out of this loop
        return true;
    }

    //Lookup channel and user ID
    $channel = find_channel($channel_id);
    
    //If no channel was found, check user
    if(!$channel){
        //Find matching user
        $user = find_member($channel_id);
        
        //Check if user was returned
        if($user){
            //User found, set as channel
            $channel = $user;
        }else{
            //No user found, we cant do anything here
            $logger->err("SEND Embed - Not a user ID or Channel ID!");
            return false;
        }
    }

    //Check if valid object
    if(!$channel){
        //This is not a channel ID or User ID
        $logger->err("SEND Embed - Channel info null!");
        return false;
    }

    //dump($channel);

    //Check if object is a member
    if(get_class($channel) == 'Discord\Parts\User\Member'){
        //Change to user object
        $channel = $channel->user;
    }elseif(get_class($channel) == 'Discord\Helpers\Collection'){
        //Change to channel object
        $channel = $channel->first();
    }
    
    //dump($channel);
    
    //Send embedded messaage
    try{
        $channel->sendMessage('', false, $embed)
            ->then(function ($response) use ($logger){
                //Message was sent successfully
            })
            ->otherwise(function ($e) use ($logger, $channel_id, $embed, $message, $discord){
                //An error was encountered
                $logger->err("Send Embed - Error: ".$e->getMessage());
                
                //Pick random time between 1 and 60 seconds
                $time = rand(1,60);
                $logger->err("Send Embed - Setting timer for next message in {$time} seconds.");
                
                //Set a timer to retry
                $discord->loop->addTimer(rand(1,60), function () use ($channel_id, $embed, $message, $logger){
                    //Send message again
                    $logger->warn("Send Embed - Retrying previous failed message...");
                    send_embed_message($channel_id, $embed, $message);
                });
            });
    }catch(Exception $e){
        //Exception caught
        $logger->err("Send Embed - Error: ".$e->getMessage());
    }
}

function send_message($channel_id,$user_id,$msgcontent,$codeblock = null,$codelang = null,$embed = null){
    //Pull in globals
    global $discord,$logger;

    //Check if channel ID is array
    if(is_array($channel_id)){
        //Loop through each channel ID
        foreach($channel_id as $channel){
            //Run each channel ID through this function
            send_message($channel,$user_id,$msgcontent,$codeblock = null,$codelang = null,$embed = null);
        }
        //Get out of this loop
        return true;
    }

    //Lookup channel and user ID
    $channel = find_channel($channel_id);
    $user = find_member($user_id);

    //Check if channel is valid
    if($channel == false && $user){
        //Invalid channel, but valid user
        $channel = $user;
    }

    //Check if valid object
    if(!$channel){
        //This is not a channel ID or User ID
        $logger->err("SEND MSG - Channel info null!");
        return false;
    }

    //Check if valid object
    if(!is_object($channel_id) && is_numeric($channel_id)){
        //Lookup channel
        $channel_id = find_channel($channel_id);
    }

    //Check if valid object
    if(!is_object($channel_id)){
        $logger->err("SEND MSG - Channel Object not passed!");
        return false;
    }

    //Check if object is a member
    if(get_class($channel) == 'Discord\Parts\User\Member'){
        //Change to user object
        $channel = $channel->user;
    }elseif(get_class($channel) == 'Discord\Helpers\Collection'){
        //Change to channel object
        $channel = $channel->first();
    }

    //Check if message is over 994 characters
    if(strlen($msgcontent) > 990){
        $msgcontent = str_split($msgcontent, 990);
    }
    //Check if message is broken into parts
    if(is_array($msgcontent)){
        //Loop through message parts
        foreach($msgcontent as $msg){
            //Send reply with our message
            try{
                if(!$codeblock){
                    $reply = $channel->sendMessage($msg)
                        ->otherwise(function ($e) use ($logger){
                            $logger->err("SEND MSG - Error: ".$e->getMessage());
                        });
                }else{
                    if($codelang){
                        $reply = $channel->sendMessage('```'.$codelang."\r\n".$msg.'```')
                            ->otherwise(function ($e) use ($logger){
                                $logger->err("SEND MSG - Error: ".$e->getMessage());
                            });
                    }else{
                        $reply = $channel->sendMessage('```'.$msg.'```')
                            ->otherwise(function ($e) use ($logger){
                                $logger->err("SEND MSG - Error: ".$e->getMessage());
                            });
                    }
                }
            }catch(Exception $e){
                $logger->err("SEND MSG - Error: ".$e->getMessage());
            }
        }
    }else{
        //Send reply with our message
        try{
            if($embed){
                //$reply = $channel->sendMessage(null, false, $embed);
                $channel->sendMessage(null, false, $embed)
                    ->then(function ($response){
                        //Resolve promise
                        //dump($response);
                    })
                    ->otherwise(function ($e) use ($logger){
                        $logger->err("SEND MSG - Error: ".$e->getMessage());
                    });
            }else{
                if(!$codeblock){
                    $reply = $channel->sendMessage($msgcontent)
                        ->otherwise(function ($e) use ($logger){
                            $logger->err("SEND MSG - Error: ".$e->getMessage());
                        });
                }else{
                    if($codelang){
                        $reply = $channel->sendMessage('```'.$codelang."\r\n".$msgcontent.'```')
                            ->otherwise(function ($e) use ($logger){
                                $logger->err("SEND MSG - Error: ".$e->getMessage());
                            });
                    }else{
                        $reply = $channel->sendMessage('```'.$msgcontent.'```')
                            ->otherwise(function ($e) use ($logger){
                                $logger->err("SEND MSG - Error: ".$e->getMessage());
                            });
                    }
                }
            }
        }catch(Exception $e){
            $logger->err("SEND MSG - Error: ".$e->getMessage());
        }
    }
    //Check for error sending
    if(!$reply){
        $logger->err('SEND MSG - Message not sent.');
    }
}

function delete_messages($channel_id, $message_num){
    //Pull in globals
    global $logger;
    //Fix message number
    $message_num = (int)$message_num;
    //Get channel object
    $channel = find_channel($channel_id);
    //Check if channel object is valid
    if(!$channel){
        $logger->warn("Delete Messasges - Could not resolve channel id ".$channel_id.".");
        return false;
    }else{
        //Set channel object
        $channel = $channel->first();
    }
    //Clear channel cache
    $channel->clearCache();
    //Get pinned messages
    $channel->getPinnedMessages()
        ->then(function ($pinned) use ($channel, $message_num, $logger) {
            //Get messages from channel
            get_channel_history($channel, $message_num)
                ->then(function ($msgs) use ($channel, $logger, $pinned) {
                    //Turn messages collection into array
                    foreach ($msgs as $key => $value){
                        //Add message into array
                        $messages[$value->id] = $value;
                    }
                    //Check if channel has any pinned messages
                    if(is_countable($pinned) && count($pinned) != 0){
                        //Loop through pinned messages
                        foreach($pinned as $pin){
                            $logger->debug("Checking for pinned message ID ".$pin->id);
                            //Check if pinned message is in messages
                            if($messages[$pin->id] != null){
                                //Remove pinned message
                                $logger->debug("Removing pinned message ID ".$pin->id);
                                unset($messages[$pin->id]);
                            }
                        }
                    }
                    //Delete messages
                    delete_bulk($channel, $messages)
                        ->otherwise(function ($error) use ($logger, $channel) {
                        //Error deleting messages
                        $logger->err("Could not delete messages from #".$channel->name.".");
                    });
                })
                ->otherwise(function ($e) use ($logger, $channel) {
                    //Error getting channel history
                    $logger->err("Could not get channel history from #".$channel->name.".");
                    return false;
                });
        })->otherwise(function ($e) use ($logger, $channel) {
            //Error getting pinned messages
            $logger->err("Could not get pinned messages from #".$channel->name.": ".$e->getMessage().".");
            return false;
        });
}

function get_channel_history($channel, $limit = 0, $messages = [], $last_msgcount = 0, $curr_msgcount = 0){
    //Pull in globals
    global $logger;
    //Fix message limit
    $limit = (int)$limit;
    //Start promise
    $deferred = new \React\Promise\Deferred();
    //Check if we hit the message limit yet
    if ($limit !== 0 && sizeof($messages) > $limit) {
        //Cut message history to limit and exit loop
        array_splice($messages, $limit);
        return new \React\Promise\FulfilledPromise($messages);
    }
    //Check if we hit the message limit for channel
    if ($last_msgcount !== 0 && $last_msgcount == $curr_msgcount) {
        //Exit loop, we have all messages
        $logger->debug("Got ".$curr_msgcount." messages");
        return new \React\Promise\FulfilledPromise($messages);
    }
    //Build message history arguments
    $lastMessage = isset($messages[sizeof($messages) - 1]) ? $messages[sizeof($messages) - 1] : null;
    $options     = ['limit' => 100, 'cache' => false];
    if ($lastMessage !== null) {
        $options['before'] = $lastMessage;
    }
    //Set last message count
    $last_msgcount = sizeof($messages);
    //Get message history from channel
    $logger->debug("Getting 100 messages from #".$channel->name);
    $channel->getMessageHistory($options)
        ->then(function ($msgs) use ($channel, $limit, $messages, $deferred, $last_msgcount, $logger) {
            //Combine current messages with total
            $messages = array_merge($messages, $msgs->toArray());
            //Loop through the next batch of 100 messages
            $logger->debug("Got ".count($messages)." messages");
            get_channel_history($channel, $limit, $messages, $last_msgcount, sizeof($messages))
                ->then(function ($messages) use ($deferred){
                    //Resolve promise
                    $deferred->resolve($messages);
                })
                ->otherwise(function () use ($messages, $deferred){
                    //Resolve promise
                    $deferred->resolve($messages);
                });
        })
        ->otherwise(function ($error) use ($deferred, $messages){
            //Resolve promise
            $deferred->resolve($messages);
        });
    //Return promise
    return $deferred->promise();
}

function delete_bulk($channel, $messages){
    //Pull in globals
    global $logger;
    //Start promise
    $deferred = new \React\Promise\Deferred();
    //Take first 100 messages
    $msgs = array_splice($messages, 100);
    //Delete first set of messages
    $logger->debug("Deleting ".count($messages)." messages from #".$channel->name);
    $channel->deleteMessages($messages)
        ->then(function () use ($channel, $msgs, $deferred, $logger){
            //Check if all messages have been deleted
            if (sizeof($msgs) <= 0) {
                //Exit loop
                return $deferred->resolve();
            }
            //Wait a second for rate limiting
            $logger->debug("Deleted ".count($msgs)." messages from #".$channel->name);
            sleep(1);
            //Loop through the next batch of 100 messages
            delete_bulk($channel, $msgs)
                ->then(function () use ($deferred){
                    //Resolve promise
                    $deferred->resolve();
                })
                ->otherwise(function ($error) use ($deferred){
                    //Resolve promise
                    $deferred->reject($error);
                });
        })
        ->otherwise(function ($error) use ($deferred){
            //Resolve promise
            $deferred->reject($error);
        });
    //Return promise
    return $deferred->promise();
}

function multiban($user_ids, $channel_id, $admin_id, $guild){
    //Pull in globals
    global $logger, $discord;
    
    //Start promise
    $deferred = new \React\Promise\Deferred();
    
    //Check if all users have been banned
    if (sizeof($user_ids) <= 0) {
        //Exit loop
        return new \React\Promise\FulfilledPromise();
    }
    
    //Take next user id out
    $user_id_list = array_splice($user_ids, 1);
    $user_id = $user_ids[0];
    
    //Check if user id is in cache
    $discord->users->fetch($user_id)
        ->then(function ($user) {
            //Send back user
            return $user;
        })
        ->otherwise(function () {
            //User does not exist or no guilds in common
            return false;
        })
        ->done(function ($user) use ($guild, $user_id, $channel_id, $admin_id, $discord, $deferred, $user_id_list, $logger) {
            //Check if user object is valid
            if(!$user){
                //Set message
                $msgcontent2 = "user ID {$user_id} on server {$guild->name}";
                
                //Build member object
                $member = $discord->factory(\Discord\Parts\User\Member::class, ['user' => ['id' => $user_id], 'guild_id' => $guild->id]);
                $member->id = $user_id;
            }else{
                //Set message
                $msgcontent2 = "user {$user->username} on server {$guild->name}";
                
                //Build member object
                $member = $discord->factory(\Discord\Parts\User\Member::class, ['user' => ['id' => $user_id], 'guild_id' => $guild->id]);
                $member->id = $user_id;
            }
            //Ban user
            $member->ban()
                ->then(function ($info) use ($guild, $channel_id, $admin_id, $discord, $msgcontent2, $deferred, $user_id_list, $logger) {
                    //User was banned successfully, build embed
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':white_check_mark: MultiBan',
                        'description' => "Successfully banned {$msgcontent2}",
                        'timestamp' => false,
                    ]);
                    
                    //Send embed message
                    send_embed_message($channel_id, $embed);
                    
                    //Wait a second for rate limiting
                    sleep(3);
                    
                    //Loop through the next user
                    multiban($user_id_list, $channel_id, $admin_id, $guild)
                        ->then(function () use ($deferred){
                            //Resolve promise
                            $deferred->resolve();
                        })
                        ->otherwise(function ($error) use ($deferred, $logger){
                            //Resolve promise
                            $logger->err("Multi Ban User - Error: ".$error);
                            $deferred->reject($error);
                        });
                })
                ->otherwise(function ($info) use ($channel_id, $admin_id, $discord, $msgcontent2, $deferred, $logger) {
                    //User could not be banned
                    $logger->err("Ban User - Error: ".$info->getMessage());

                    //User could not be banned, build embed
                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':x: MultiBan',
                        'description' => "Could not ban {$msgcontent2}",
                        'timestamp' => false,
                    ]);
                
                    //Send embed message
                    send_embed_message($channel_id, $embed);
                    
                    //Resolve promise
                    $deferred->reject($error);
                });
        });
    
    //Return promise
    return $deferred->promise();
}

function update_invites(){
    //Pull in globals
    global $discord,$logger,$f3,$db;
    //Get all guilds bot is in
    $guildInfo = $discord->guilds;

    $f3->set('curr_code',array());

    //Parse guild info from API and compare
    if (is_countable($guildInfo) && count($guildInfo) !== 0){
        //Loop through guilds
        foreach ($guildInfo as $guild){
            try{
                //Get invites for guild
                $guild->getInvites()->then(function ($invites) use ($guildInvites, $guild, $logger, $curr_code, $db, $f3, $discord){
                    $logger->debug("INVITES - Got ".count($invites)." for ".$guild->name.".");
                    if(is_countable($invites) && count($invites) !== 0){
                        foreach($invites as $invite){
                            $guildInvites[$invite->code]['code'] = $invite->code;
                            $guildInvites[$invite->code]['max_age'] = $invite->max_age;
                            $guildInvites[$invite->code]['created_at'] = $invite->created_at->setTimezone('America/New_York')->toDateTimeString();
                            $guildInvites[$invite->code]['temporary'] = $invite->temporary;
                            $guildInvites[$invite->code]['uses'] = $invite->uses;
                            $guildInvites[$invite->code]['inviter_id'] = $invite->inviter->id;
                            $guildInvites[$invite->code]['inviter_name'] = $invite->inviter->username;
                            $guildInvites[$invite->code]['max_uses'] = $invite->max_uses;
                            $guildInvites[$invite->code]['channel_id'] = $invite->channel->id;
                            $guildInvites[$invite->code]['channel_name'] = $invite->channel->name;
                            $guildInvites[$invite->code]['revoked'] = $invite->revoked;
                        }
                    }
                    $guild_id = $guild->id;
                    
                    //Parse invite info
                    if (is_countable($guildInvites) && count($guildInvites) !== 0){
                        //Set admin channel IDs or owner ID if not set
                        $admin_chan = get_adminchan($guild_id);
                        //Loop through invites from API
                        foreach ($guildInvites as $invite){
                            //Store current invite info
                            $logger->debug("INVITES - Parsing API invite code ".$invite["code"]." for ".$guild->name." used ".$invite["uses"]." times.");
                            $maxage = $invite["max_age"];
                            $code = $invite["code"];
                            $created_at = $invite["created_at"];
                            $uses = $invite["uses"];
                            $inviter_id = $invite["inviter_id"];
                            $inviter_name = $invite["inviter_name"];
                            $channel_id = $invite["channel_id"];
                            $channel_name = $invite["channel_name"];
        
                            //Check if inviter name is null
                            if(!$inviter_name){
                                //This was generated by someone viewing widget
                                $inviter_name = 'Discord Widget';
                            }
                            
                            //Store code in global cache
                            $f3->set('curr_code.'.$code, "code-".$code);
        
                            try{
                                //Get invite data from db
                                $db_inviteinfo = $db->exec('SELECT id,guild,code,maxage,created_at,uses,inviter_id,channel_id FROM invites WHERE guild = ? AND code = ? AND bot_id = ?',array(1=>$guild_id,2=>$code,3=>$f3->get('instance')));
                            }catch(Exception $e){
                                $logger->err("Error getting invites DB.");
                            }
                            
                            //Check if this code exists in the db
                            if(is_countable($db_inviteinfo) && count($db_inviteinfo) !== 0){
                                //Check uses to see if the number has gone up
                                if ($uses != $db_inviteinfo[0]["uses"]){
                                    //Amount of uses has changed, update in db
                                    try{
                                        $update = $db->exec('UPDATE invites SET uses = ? WHERE code = ? AND guild = ? AND bot_id = ?',array(1=>$uses,2=>$code,3=>$guild_id,4=>$f3->get('instance')));
                                    }catch(Exception $e){
                                        $logger->err("Error updating invites in DB.");
                                    }
                                    //Build message
                                    $message = "Invite code [".$code."] created by ".$inviter_name." has been used for channel ".$channel_name;
                                    //Build embed message
                                    $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                                        ->setTitle("Invite code used")
                                        ->setDescription("**Code:** {$code} | **Inviter:** {$inviter_name} | **Channel:** {$channel_name}")
                                        ->setColor(16763904)
                                        ->setTimestamp();
                                    //Send notification
                                    send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$guild_id);
                                }
                            }else{
                                //Code is not in the database, add it
                                $logger->info("Inserting into DB: [G:".$guild_id."]  [C:".$code."]  [A:".$maxage."]  [CR:".$created_at."]  [U:".$uses."]  [I:".$inviter_id."]  [C:".$channel_id."]");
                                try{
                                    $insert = $db->exec('INSERT INTO invites (guild, code, maxage, created_at, uses, inviter_id, channel_id, bot_id) VALUES(?, ?, ?, ?, ?, ?, ?, ?)',array(1=>$guild_id,2=>$code,3=>$maxage,4=>$created_at,5=>$uses,6=>$inviter_id,7=>$channel_id,8=>$f3->get('instance')));
                                }catch(Exception $e){
                                    $logger->err("Error adding invites in DB.");
                                }
                                //Build message
                                $message = "New invite code added by user ".$inviter_name." [".$code."] for channel ".$channel_name." with expiry of: ".$maxage;
                                //Build embed message
                                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                                    ->setTitle("New invite code added")
                                    ->setDescription("**Code:** {$code} | **Inviter:** {$inviter_name} | **Channel:** {$channel_name} | **Expires:** {$maxage} seconds")
                                    ->setColor(16763904)
                                    ->setTimestamp();
                                //Send notification
                                send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$guild_id);
                            }
                        }
                    }
                    
                    //Keep database in sync with API data
                    $logger->debug("INVITES - Checking database for ".$guild->name.".");
                    $db_invites = $db->exec('SELECT id,guild,code,maxage,created_at,uses,inviter_id,channel_id FROM invites WHERE guild = ? AND bot_id = ?', array(1=>$guild_id,2=>$f3->get('instance')));
                    $curr_code = $f3->get('curr_code');
                    //Parse invites from database and compare
                    if (is_countable($db_invites) && count($db_invites) !== 0){
                        //Loop through db invites
                        foreach ($db_invites as $db_invite){
                            //Set admin channel IDs or owner ID if not set
                            $admin_chan = get_adminchan($db_invite['guild']);
                            //Store database invite info
                            $logger->debug("INVITES - Checking stored code ".$db_invite["code"]." for ".$guild->name." used ".$db_invite["uses"]." times.");
                            $db_code = $db_invite["code"];
                            
                            //Check if invite code still exists on discord
                            if ($curr_code[$db_code] == ""){
                                //Code was removed/expired, remove from db
                                $delete = $db->exec('DELETE FROM invites WHERE code = ? AND bot_id = ?',array(1=>$db_code,2=>$f3->get('instance')));
                                //Build message
                                $message = "Invite code [".$db_code."] removed/expired.";
                                //Build embed message
                                $embed = $discord->factory(\Discord\Parts\Embed\Embed::class)
                                    ->setTitle("Invite code removed/expired")
                                    ->setDescription("**Code:** {$db_code}")
                                    ->setColor(16763904)
                                    ->setTimestamp();

                                //Send notification
                                send_notification($admin_chan,$f3->get('bot_owners'),$message,$embed,$guild_id);
                            }
                        }
                    }
                })
                ->otherwise(function (\Exception $x) {
                    //Throw error
                    throw $x;
                });
            }catch(Exception $e){
                //Permission error or something went wrong
                $logger->warn("INVITES - No permission to check invites in guild ".$guild->name.".");
                $guildInvites = array();
            }
        }
    }
}