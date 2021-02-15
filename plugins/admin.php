<?php

class admin{
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
     * @var
     */
    var $cache;

    /**
     * @param $f3
     * @param $discord
     * @param $logger
     */
    function init($f3, $discord, $logger){
        global $cache;

        $this->f3 = $f3;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    function onLog(){
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData){
    }

    function onMessageAdmin($msgData){
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
            //Check command
            try{
                //Attempt to run admin function
                $func = 'admin_'.$command['command'];
                $this->$func($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to);
            }catch(Exception $e){
                //Admin function does not exist or there was an error
                $this->logger->info("ADMIN - Unknown command: ".$command['command']);
            }
        }
    }

    //Send embed with command response
    private function command_response($status, $command, $message, $reply_to){
        //Apply status emoji
        switch ($status) {
            case 'error':
                $emoji = ':x:';
                break;
                
            case 'warning':
                $emoji = ':warning:';
                break;
                
            case 'success':
                $emoji = ':white_check_mark:';
                break;
                
            default:
                $emoji = '';
        }
        
        //Build response embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => "{$emoji} {$command}",
            'description' => $message,
            'timestamp' => false,
        ]);
        
        //Send embed message with response
        send_embed_message($reply_to, $embed);
    }

    //Set game
    private function admin_setgame($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Set game name
        $game = $this->discord->factory(\Discord\Parts\User\Game::class, [
            'name' => $msgarg,
            'type' => 0,
        ]);
        
        //Apply setting
        $this->discord->updatePresence($game);
        
        //Send response
        $this->command_response('success', 'Set Game', "Game set to: {$msgarg}", $reply_to);
        return false;
    }

    //List Guilds
    private function admin_listguilds($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Get guild list
        $guilds = $this->discord->guilds;
        
        //Make sure we have at least one guild
        if (is_countable($guilds) && count($guilds) !== 0){
            //Loop through guilds
            foreach ($guilds as $guild){
                //Build fields
                $fields[] = ['name' => "{$guild->name}", 'value' => "ID: {$guild->id}", 'inline' => false];
            }
        }
        
        //Build embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => ':wrench: List Servers',
            'timestamp' => false,
            'fields' => $fields
        ]);
        
        //Send embed message
        send_embed_message($reply_to, $embed);
        return false;
    }

    //List channels
    private function admin_listchannels($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Get specified guild id
        if ($msgarg){
            //Guild was passed
            $guildArg = $msgarg;
        }else{
            //No guild passed
            $guildArg = null;
        }
        
        //Check if sent in DM without guild id
        if($guildName == "PM" && $guildArg == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'List Channels', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }
        
        //Determine which guild id to use
        if($guildArg){
            //Use specified id
            $guild_id = $guildArg;
        }else{
            //Use guild id from message
            $guild_id = $guildID;
        }
        
        //Get guild
        $guild = $this->discord->guilds->get('id', $guild_id);
        
        //Check for valid guild
        if(!$guild){
            //Invalid guild ID
            $this->command_response('error', 'List Channels', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
       
        //Get channel list
        $channels = $guild->channels;
        
        //Make sure we have at least one channel
        if(is_countable($channels) && count($channels) !== 0){
            //Loop through channels
            foreach ($channels as $channel){
                //Get channel type
                $channel_type = get_channel_type($channel->type);

                //Build fields
                $fields[] = ['name' => "{$channel->name} ({$channel_type})", 'value' => "ID: {$channel->id}", 'inline' => false];
            }
        }
        
        //Build embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => ':wrench: List Channels',
            'timestamp' => false,
            'fields' => $fields
        ]);
        
        //Send embed message
        send_embed_message($reply_to, $embed);
        return false;
    }

    //List users
    private function admin_listusers($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Get specified guild id
        if ($msgarg){
            //Guild was passed
            $guildArg = $msgarg;
        }else{
            //No guild passed
            $guildArg = null;
        }
        
        //Check if sent in DM without guild id
        if($guildName == "PM" && $guildArg == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'List Users', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }
        
        //Determine which guild id to use
        if($guildArg){
            //Use specified id
            $guild_id = $guildArg;
        }else{
            //Use guild id from message
            $guild_id = $guildID;
        }
        
        //Get guild
        $guild = $this->discord->guilds->get('id', $guild_id);
        
        //Check for valid guild
        if(!$guild){
            //Invalid guild ID
            $this->command_response('error', 'List Users', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Get user list
        $members = $guild->members;
        $field_count = 0;
        $user_count = 0;
        $fields = array();
        
        //Check if user list contains more than 25 users
        if(is_countable($members) && count($members) > 25){
            //More than 25 users, loop through 25 at a time
            foreach($members as $user){
                //Check if 25 users have been added
                if($field_count == 25){
                    //We have 25 users, build embed
                    $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':wrench: List Users',
                        'timestamp' => false,
                        'fields' => $fields
                    ]);

                    //Send embed message
                    send_embed_message($reply_to, $embed);
                    
                    //Clear counter and reset fields
                    $field_count = 0;
                    $fields = array();
                }
                
                //Build fields
                $fields[] = ['name' => "{$user->username}", 'value' => "ID: {$user->id}", 'inline' => false];

                //Increment counters
                $field_count++;
                $user_count++;

                //Check for end of list
                if(is_countable($members) && $user_count == count($members)){
                    //Reached end of list, build embed for remainder
                    $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                        'title' => ':wrench: List Users',
                        'timestamp' => false,
                        'fields' => $fields
                    ]);

                    //Send embed message
                    send_embed_message($reply_to, $embed);
                }
            }
        }else{
            //Less than 25 users, loop through user list
            foreach($members as $user){
                //Build fields
                $fields[] = ['name' => "{$user->username}", 'value' => "ID: {$user->id}", 'inline' => false];
            }

            //Build embed
            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                'title' => ':wrench: List Users',
                'timestamp' => false,
                'fields' => $fields
            ]);
            
            //Send embed message
            send_embed_message($reply_to, $embed);
        }
    }

    //Ban user
    private function admin_ban($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check amount of arguments
        if(is_countable($arguments) && count($arguments) == 2){
            //2 args, check if first is guild name or id
            if(is_numeric($arguments[0])){
                //Guild id passed
                $guild_id = $arguments[0];
                $guild_name = null;
            }else{
                //Guild name passed
                $guild_id = null;
                $guild_name = $arguments[0];
            }                        
            
            //Set id to ban
            $ban_id = $arguments[1];
        }elseif(is_countable($arguments) && count($arguments) == 1){
            //1 arg, set vars
            $guild_id = $guildID;
            $guild_name = null;
            $ban_id = $msgarg;
        }else{
            //No args, set vars
            $guild_id = null;
            $guild_name = null;
        }

        //Check if sent in DM without guild id or just no args
        if($guildName == "PM" && $guild_id == null && $guild_name == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'Ban User', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }

        //Determine if guild id or name should be used
        if($guild_name !== null){
            //Use guild name
            $guild = $this->discord->guilds->get('name', $guild_name);
        }else{
            //Use guild id
            $guild = $this->discord->guilds->get('id', $guild_id);
        }

        //Check for valid guild
        if(!$guild){
            //Invalid guild ID
            $this->command_response('error', 'Ban User', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Check if user id has public info
        $this->discord->users->fetch($ban_id)->then(function ($user) use ($guild, $ban_id, $reply_to){
            //Send back user
            return $user;
        })->otherwise(function ($user) use ($guild, $ban_id, $reply_to) {
            //User does not exist or no guilds in common
            return false;
        })->done(function ($user) use ($guild, $ban_id, $reply_to) {
            //Check if user object is valid
            if(!$user){
                //Set message
                $msgcontent2 = "user ID {$ban_id} on server {$guild->name}";
                
                //Build member object and insert id to ban
                $member = $this->discord->factory(\Discord\Parts\User\Member::class, ['user' => ['id' => $ban_id], 'guild_id' => $guild->id]);
                $member->id = $ban_id;
            }else{
                //Set message
                $msgcontent2 = "user {$user->username} on server {$guild->name}";
                
                //Build member object and insert id to ban
                $member = $this->discord->factory(\Discord\Parts\User\Member::class, ['user' => ['id' => $ban_id], 'guild_id' => $guild->id]);
                $member->id = $ban_id;
            }
            //Ban user
            $member->ban()->then(function ($info) use ($reply_to, $msgcontent2) {
                //User was banned successfully
                $this->command_response('success', 'Ban User', "Successfully banned {$msgcontent2}", $reply_to);
                return false;
            })->otherwise(function ($info) use ($reply_to, $msgcontent2) {
                //User could not be banned
                $this->command_response('error', 'Ban User', "Could not ban {$msgcontent2}", $reply_to);
                return false;
            });
        });
    }

    //Ban list of users
    private function admin_multiban($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check amount of arguments
        if(is_countable($arguments) && count($arguments) >= 2){
            //2 or more args, set vars
            $guild_id = $guildID;
            $guild_name = $guildName;
            $ban_ids = $arguments;
        }else{
            //No args, set vars
            $guild_id = null;
            $guild_name = $guildName;
            $ban_ids = $arguments;
        }

        //Check if sent in DM without guild id or just no args
        if($guild_name == "PM" && $guild_id == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'MultiBan', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }

        //Determine if guild id or name should be used
        if($guild_name !== null){
            //Use guild name
            $guild = $this->discord->guilds->get('name', $guild_name);
        }else{
            //Use guild id
            $guild = $this->discord->guilds->get('id', $guild_id);
        }

        //Check for valid guild
        if(!$guild){
            //Invalid guild ID
            $this->command_response('error', 'MultiBan', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Check if list is valid
        if(is_countable($ban_ids) && count($ban_ids) !== 0){
            //Run multiban
            multiban($ban_ids, $channelID, $fromID, $guild);

            //Send response
            $this->command_response('success', 'MultiBan', "Multibanning ".count($ban_ids)." users.", $reply_to);
            return false;
        }else{
            //User could not be banned
            $this->command_response('error', 'MultiBan', "No valid user IDs given!", $reply_to);
            return false;
        }
    }

    //Kick user
    private function admin_kick($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check amount of arguments
        if(is_countable($arguments) && count($arguments) == 2){
            //2 args found, guild passed
            $guild_id = $arguments[0];
            $kick_id = $arguments[1];
        }elseif(is_countable($arguments) && count($arguments) == 1){
            //1 arg passed, only user id passed
            $guild_id = $guildID;
            $kick_id = $msgarg;
        }else{
            //No args, set vars
            $guild_id = null;
        }
        
        //Check if sent in DM without guild id or just no args
        if($guildName == "PM" && $guild_id == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'Kick User', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }
        
        //Get guild instance
        $guild = $this->discord->guilds->get('id', $guild_id);
        
        //Check for valid guild
        if(!$guild){
            //Invalid guild ID
            $this->command_response('error', 'Kick User', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Get member instance
        $member = $guild->members->get('id', $kick_id);
        
        //Check for valid member
        if(!$member){
            //Invalid member ID
            $this->command_response('error', 'Kick User', "The **User ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Kick user
        $guild->members->delete($member)->then(function ($info) use ($channelID, $fromID, $guild, $member, $reply_to) {
            //User was kicked successfully
            $this->command_response('success', 'Kick User', "Successfully kicked user {$member->username} on {$guild->name}", $reply_to);
            return false;
        })->otherwise(function ($info) use ($channelID, $fromID, $guild, $member, $reply_to) {
            //User could not be kicked
            $this->command_response('error', 'Kick User', "Could not kick user {$member->username} on {$guild->name}", $reply_to);
            return false;
        });
    }

    //Send message as bot
    private function admin_sendas($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check amount of arguments
        if(is_countable($arguments) && count($arguments) >= 2){
            //2 args found, set vars
            $channel_id = $arguments[0];
            unset($arguments[0]);
            $sendmessage = implode(" ",$arguments);
        }else{
            //no args found, set vars
            $sendmessage = null;
            $channel_id = null;
        }

        //Check if sent in DM without guild id or just no args
        if($guildName == "PM" && ($sendmessage == null || $channel_id == null)){
            //Sent in DM with no guild id
            $this->command_response('error', 'Send Message', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }

        //Check if channel id is valid
        $channel = find_channel($channel_id);
        
        //Check for valid channel
        if(!$channel){
            //Invalid channel
            $this->command_response('error', 'Send Message', "The **Channel ID** you specified was not found.", $reply_to);
            return false;
        }

        //Send message
        send_message($channel_id, null, $sendmessage);

        //Send response
        $this->command_response('success', 'Send Message', "Sent message to channel {$channel_id}.", $reply_to);
        return false;
    }

    //Clear messages
    private function admin_clear($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check amount of arguments
        if(is_countable($arguments) && count($arguments) == 2){
            //2 args found, set channel id and number
            $channel_id = $arguments[0];
            $message_num = $arguments[1];
        }elseif(is_countable($arguments) && count($arguments) == 1){
            //1 arg found, set number
            $channel_id = $channelID;
            $message_num = $arguments[0];
        }else{
            //no args found, set vars
            $channel_id = null;
            $message_num = null;
        }
        
        //Check if sent in DM without guild id or just no args
        if($guildName == "PM" && $channel_id == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'Clear', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }
        
        //Delete messages
        $delete = delete_messages($channel_id, $message_num);

        //Send response
        $this->command_response('success', 'Clear', "Cleared last {$message_num} messages from channel {$channel_id}.", $reply_to);
        return false;
    }

    //Find user
    private function admin_finduser($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Get username or part of username
        if ($msgarg){
            $userArg = $msgarg;
        }else{
            $userArg = null;
        }

        //Check if sent in DM without guild id or just no args
        if($guildName == "PM" && $userArg == null){
            //Sent in DM with no guild id
            $this->command_response('error', 'Find User', "You must specify a **Server ID** for this command.", $reply_to);
            return false;
        }

        $msgcontent = "";
        $userdata = array();
        
        //Get guild object
        $guilds = $this->discord->guilds;
        
        //Get user list
        $users = $this->discord->users;
        
        //Check if users exist
        if (is_countable($users) && count($users) !== 0){
            //Loop through users
            foreach ($users as $userinfo){
                //Check for part of username
                if(stripos($userinfo->username, $userArg) !== false){
                    //Get guild info
                    $guildinfo = get_all_member_guilds($userinfo->id);
                    foreach($guildinfo as $guild){
                        //Store member data in array
                        $userdata[$userinfo->id][$guild->guild_id] = $guild;                                        
                    }
                }
            }
        }
        
        //Check if users were returned
        if (is_countable($userdata) && count($userdata) == 0){
            //No users returned
            $this->command_response('warning', 'Find User', "No users found matching your search.", $reply_to);
            return false;
        }

        //Loop through each user
        foreach($userdata as $memberarr){
            $member_guilds = array();
            foreach($memberarr as $member){
                $guild = $this->discord->guilds->get('id', $member->guild_id);
                $member_guilds[] = $guild->name;
            }
            foreach($memberarr as $member){
                //Start array
                $fields = array();
                
                //Check username and ID
                $fields[] = ['name' => 'Username', 'value' => "{$member->user->username}", 'inline' => true];
                $fields[] = ['name' => 'ID', 'value' => "{$member->user->id}", 'inline' => true];
                
                //Check status
                if(!$member->status){
                    $fields[] = ['name' => 'Status', 'value' => "offline", 'inline' => true];
                }else{
                    $fields[] = ['name' => 'Status', 'value' => "{$member->status}", 'inline' => true];
                }
                
                //Check if playing game
                if($member->game->name){
                    $fields[] = ['name' => 'Game', 'value' => "{$member->game->name}", 'inline' => true];
                }
                
                //Check last online and last spoke
                if($member->status == 'online'){
                    //User is online
                    $fields[] = ['name' => 'Last Online', 'value' => date('M d Y h:i A'), 'inline' => true];

                    if($lastseen = get_last_seen($member->user->id)){
                        if($lastseen['last_spoke']){
                            $fields[] = ['name' => 'Last Spoke', 'value' => date('M d Y h:i A', strtotime($lastseen['last_spoke'])), 'inline' => true];
                        }
                    }                                                
                }else{
                    //User is offline
                    if($lastseen = get_last_seen($member->user->id)){
                        if($lastseen['last_online']){
                            $fields[] = ['name' => 'Last Online', 'value' => date('M d Y h:i A', strtotime($lastseen['last_online'])), 'inline' => true];
                        }
                        if($lastseen['last_spoke']){
                            $fields[] = ['name' => 'Last Spoke', 'value' => date('M d Y h:i A', strtotime($lastseen['last_spoke'])), 'inline' => true];
                        }
                    }
                }

                //Check name history
                if($namehistory = get_name_history($member->user->id)){
                    $fields[] = ['name' => 'Name History', 'value' => "{$namehistory}", 'inline' => false];
                }

                //Check servers
                $server_l = implode(", ", $member_guilds);
                $fields[] = ['name' => 'Servers', 'value' => "{$server_l}", 'inline' => false];

                //Build embed
                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                    'title' => ':information_source: User Info',
                    'timestamp' => $member->joined_at,
                    'fields' => $fields,
                    'thumbnail' => $this->discord->factory(Discord\Parts\Embed\Image::class, [
                        'url' => $member->user->avatar
                    ]),
                    'footer' => $this->discord->factory(Discord\Parts\Embed\Footer::class, [
                        'text' => "Joined at"
                    ]),                                        
                ]);
                
                //Send embed message
                send_embed_message($reply_to, $embed);
            }
        }
    }

    //Blacklist channel
    private function admin_blacklist($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if channel id is valid
        $channel = find_channel($msgarg);
        
        //Check for valid channel
        if(!$channel){
            //Invalid channel
            $this->command_response('error', 'Blacklist Channel', "The **Channel ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Get channel object
        $channel = $channel->first();
        
        //Add channel to blacklist
        if($this->cache->add('blacklist', $msgarg, $guildID)){
            //Success
            $this->command_response('success', 'Blacklist Channel', "Channel {$channel->name} added to blacklist.", $reply_to);
            return false;
        }else{
            //Channel already added
            $this->command_response('warning', 'Blacklist Channel', "Channel {$channel->name} is already in blacklist.", $reply_to);
            return false;
        }
    }

    //Un-blacklist channel
    private function admin_unblacklist($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if channel id is valid
        $channel = find_channel($msgarg);
        
        //Check for valid channel
        if(!$channel){
            //Invalid channel
            $this->command_response('error', 'Un-Blacklist Channel', "The **Channel ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Get channel object
        $channel = $channel->first();
        
        //Remove channel from blacklist
        if($this->cache->remove('blacklist', $msgarg, $guildID)){
            //Success
            $this->command_response('success', 'Un-Blacklist Channel', "Channel {$channel->name} removed from blacklist.", $reply_to);
            return false;
        }else{
            //Channel not added
            $this->command_response('warning', 'Un-Blacklist Channel', "Channel {$channel->name} is not in blacklist.", $reply_to);
            return false;
        }
    }

    //Add admin
    private function admin_addadmin($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if passed both user id and admin alias name
        if(is_countable($arguments) && count($arguments) == 2){
            //Passed user id and name, check if user id is valid
            $admin_id = $this->discord->users->get('id', $arguments[0]);
            
            //Check for valid user
            if(!$admin_id){
                //User ID not valid
                $this->command_response('error', 'Add Admin', "The **User ID** you specified was not found.", $reply_to);
                return false;
            }

            //Check if admin is already in list
            if(!$this->cache->search($arguments[0],'admins')){
                //Admin name given
                if($this->cache->add('admins' ,$arguments[0], $guildID, $arguments[1])){
                    //Success
                    $this->command_response('success', 'Add Admin', "User {$admin_id->username} added to admin list.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Add Admin', "Failed to add {$admin_id->username} to the admin list.", $reply_to);
                    return false;
                }                     
            }else{
                //User already added
                $this->command_response('warning', 'Add Admin', "User {$admin_id->username} is already in admin list.", $reply_to);
                return false;
            }
        }
        
        //Check if passed only user name
        if(is_countable($arguments) && count($arguments) == 1){
            //Only passed user id, check if user id is valid
            $admin_id = $this->discord->users->get('id', $arguments[0]);
            
            //Check for valid user
            if(!$admin_id){
                //User ID not valid
                $this->command_response('error', 'Add Admin', "The **User ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Check if admin is already in list
            if(!$this->cache->search($arguments[0], 'admins')){
                //Admin name not given
                if($this->cache->add('admins', $arguments[0], $guildID, $admin_id->username)){
                    //Success
                    $this->command_response('success', 'Add Admin', "User {$admin_id->username} added to admin list.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Add Admin', "Failed to add {$admin_id->username} to the admin list.", $reply_to);
                    return false;
                }                           
            }else{
                //User already added
                $this->command_response('warning', 'Add Admin', "User {$admin_id->username} is not in admin list.", $reply_to);
                return false;
            }
        }
    }

    //Remove admin
    private function admin_removeadmin($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if user id is valid
        $admin_id = $this->discord->users->get('id', $msgarg);
        
        //Check for valid user
        if(!$admin_id){
            //User ID not valid
            $this->command_response('error', 'Remove Admin', "The **User ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Remove user from admin list
        if($this->cache->search($msgarg, 'admins')){
            //User found
            if($this->cache->remove('admins', $msgarg)){
                //Success
                $this->command_response('success', 'Remove Admin', "User {$admin_id->username} removed from admin list.", $reply_to);
                return false;
            }else{
                //Failed
                $this->command_response('error', 'Remove Admin', "User {$admin_id->username} could not be removed from admin list.", $reply_to);
                return false;
            }
        }else{
            //User not in admin list
            $this->command_response('warning', 'Remove Admin', "User {$admin_id->username} is not in admin list.", $reply_to);
            return false;
        }
    }

    //Get user avatar
    private function admin_getavatar($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if user id is valid
        $user_id = $this->discord->users->get('id', $msgarg);

        //Check for valid user
        if(!$user_id){
            //User ID not valid
            $this->command_response('error', 'User Avatar Info', "The **User ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Build embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => ':mag: User Avatar Info',
            'fields' => [
                ['name' => 'Links', 'value' => "[Google reverse image search](https://www.google.com/searchbyimage?image_url={$user_id->avatar}&btnG=Search+by+image&encoded_image=&image_content=&filename=&hl=en)", 'inline' => true],
            ],
            'image' => $this->discord->factory(Discord\Parts\Embed\Image::class, [
                'url' => "{$user_id->avatar}"
            ]),
            'timestamp' => false,
        ]);
        
        //Send message to chat
        send_embed_message($reply_to,$embed);
    }

    //Get user info
    private function admin_getuserinfo($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if user id is valid
        $user_id = find_member($msgarg);

        //Check for valid user
        if(!$user_id){
            //User ID not valid
            $this->command_response('error', 'User Info', "The **User ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Start array
        $fields = array();
        $member = $user_id;
        
        //Check username and ID
        $fields[] = ['name' => 'Username', 'value' => "{$member->user->username}", 'inline' => true];
        $fields[] = ['name' => 'ID', 'value' => "{$member->user->id}", 'inline' => true];
        
        //Check status
        if(!$member->status){
            $fields[] = ['name' => 'Status', 'value' => "offline", 'inline' => true];
        }else{
            $fields[] = ['name' => 'Status', 'value' => "{$member->status}", 'inline' => true];
        }
        
        //Check if playing game
        if($member->game->name){
            $fields[] = ['name' => 'Game', 'value' => "{$member->game->name}", 'inline' => true];
        }
        
        //Check last online and last spoke
        if($member->status == 'online'){
            //User is online
            $fields[] = ['name' => 'Last Online', 'value' => date('M d Y h:i A'), 'inline' => true];

            if($lastseen = get_last_seen($member->user->id)){
                if($lastseen['last_spoke']){
                    $fields[] = ['name' => 'Last Spoke', 'value' => date('M d Y h:i A', strtotime($lastseen['last_spoke'])), 'inline' => true];
                }
            }                                                
        }else{
            //User is offline
            if($lastseen = get_last_seen($member->user->id)){
                if($lastseen['last_online']){
                    $fields[] = ['name' => 'Last Online', 'value' => date('M d Y h:i A', strtotime($lastseen['last_online'])), 'inline' => true];
                }
                if($lastseen['last_spoke']){
                    $fields[] = ['name' => 'Last Spoke', 'value' => date('M d Y h:i A', strtotime($lastseen['last_spoke'])), 'inline' => true];
                }
            }
        }

        //Check name history
        if($namehistory = get_name_history($member->user->id)){
            $fields[] = ['name' => 'Name History', 'value' => "{$namehistory}", 'inline' => false];
        }

        //Build embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => ':information_source: User Info',
            'timestamp' => $member->joined_at,
            'fields' => $fields,
            'thumbnail' => $this->discord->factory(Discord\Parts\Embed\Image::class, [
                'url' => $member->user->avatar
            ]),
            'footer' => $this->discord->factory(Discord\Parts\Embed\Footer::class, [
                'text' => "Joined at"
            ]),                                        
        ]);
        
        //Send message to chat
        send_embed_message($reply_to, $embed);
    }

    //Set admin channel
    private function admin_addadminchan($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if passed guild id and channel id
        if(is_countable($arguments) && count($arguments) == 2){
            //Check if guild id is valid
            $admin_guild = $this->discord->guilds->get('id', $arguments[0]);
            
            //Check if given guild is valid
            if(!$admin_guild){
                //Guild ID not valid
                $this->command_response('error', 'Add Admin Channel', "The **Server ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Check channel ID
            $admin_chan = find_channel($arguments[1]);
            
            //Check if given channel is valid
            if(!$admin_chan){
                //Channel not valid
                $this->command_response('error', 'Add Admin Channel', "The **Channel ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Get channel object
            $admin_chan = $admin_chan->first();
            
            //Check if admin channel already specified for guild
            if(!$this->cache->search([$arguments[0], $arguments[1]], 'adminchan')){
                //Admin channel not already added for guild, add it
                if($this->cache->add('adminchan', $arguments[0], $arguments[1])){
                    //Success
                    $this->command_response('success', 'Add Admin Channel', "Added channel {$admin_chan->name} as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Add Admin Channel', "Failed to add channel {$admin_chan->name} as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }
            }else{
                //Channel already added for guild
                $this->command_response('warning', 'Add Admin Channel', "Channel {$admin_chan->name} is already an admin channel for guild {$admin_guild->name}.", $reply_to);
                return false;
            }
        }
        
        //Check if only passed channel id
        if(is_countable($arguments) && count($arguments) == 1){
            //Check if channel id is valid
            $admin_chan = find_channel($arguments[0]);
            $admin_guild = $this->discord->guilds->get('id', $guildID);
            
            //Check if given channel is valid
            if(!$admin_chan){
                //Channel not valid
                $this->command_response('error', 'Add Admin Channel', "The **Channel ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Get channel object
            $admin_chan = $admin_chan->first();
            
            //Check if admin channel already specified for guild
            if(!$this->cache->search([$guildID, $arguments[0]], 'adminchan')){
                //Admin channel not already added for guild, add it
                if($this->cache->add('adminchan', $guildID, $arguments[0])){
                    //Success
                    $this->command_response('success', 'Add Admin Channel', "Added channel {$admin_chan->name} as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Add Admin Channel', "Failed to add channel {$admin_chan->name} as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }
            }else{
                //Channel already added for guild
                $this->command_response('warning', 'Add Admin Channel', "Channel {$admin_chan->name} is already an admin channel for guild {$admin_guild->name}.", $reply_to);
                return false;
            }
        }
    }

    //Remove admin channel
    private function admin_removeadminchan($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if passed guild id and channel id
        if(is_countable($arguments) && count($arguments) == 2){
            //Check if guild id is valid
            $admin_guild = $this->discord->guilds->get('id', $arguments[0]);
            
            //Check if given guild is valid
            if(!$admin_guild){
                //Guild ID not valid
                $this->command_response('error', 'Remove Admin Channel', "The **Server ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Check channel ID
            $admin_chan = find_channel($arguments[1]);
            
            //Check if given channel is valid
            if(!$admin_chan){
                //Channel not valid
                $this->command_response('error', 'Remove Admin Channel', "The **Channel ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Get channel object
            $admin_chan = $admin_chan->first();
            
            //Check if admin channel is specified for guild
            if($this->cache->search([$arguments[0], $arguments[1]], 'adminchan')){
                //Admin channel added for guild, remove it
                if($this->cache->remove('adminchan', $arguments[0], $arguments[1])){
                    //Success
                    $this->command_response('success', 'Remove Admin Channel', "Channel {$admin_chan->name} removed as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Remove Admin Channel', "Channel {$admin_chan->name} could not be removed as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }
            }else{
                //Channel not set for guild
                $this->command_response('warning', 'Remove Admin Channel', "Channel {$admin_chan->name} is not an admin channel for guild {$admin_guild->name}.", $reply_to);
                return false;
            }
        }
        
        //Check if only passed channel id
        if(is_countable($arguments) && count($arguments) == 1){
            //Check if channel id is valid
            $admin_chan = find_channel($arguments[0]);
            $admin_guild = $this->discord->guilds->get('id', $guildID);
            
            //Check if given guild is valid
            if(!$admin_guild){
                //Guild ID not valid
                $this->command_response('error', 'Remove Admin Channel', "The **Server ID** you specified was not found.", $reply_to);
                return false;
            }
            
            //Get channel object
            $admin_chan = $admin_chan->first();
            
            //Check if admin channel is specified for guild
            if($this->cache->search([$guildID, $arguments[0]], 'adminchan')){
                //Admin channel added for guild, remove it
                if($this->cache->remove('adminchan', $guildID, $arguments[0])){
                    //Success
                    $this->command_response('success', 'Remove Admin Channel', "Channel {$admin_chan->name} removed as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }else{
                    //Failed
                    $this->command_response('error', 'Remove Admin Channel', "Channel {$admin_chan->name} could not be removed as admin channel for guild {$admin_guild->name}.", $reply_to);
                    return false;
                }
            }else{
                //Channel not set for guild
                $this->command_response('warning', 'Remove Admin Channel', "Channel {$admin_chan->name} is not an admin channel for guild {$admin_guild->name}.", $reply_to);
                return false;
            }
        }
    }

    //Set notification user and words
    private function admin_setnotification($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if guild id is set
        if(!$guildID){
            //Get guild
            $guild_id = $this->discord->guilds->get('id', $arguments[0]);
            $user_id = $this->discord->users->get('id', $fromID);
            
            //Remove guild id from array
            unset($arguments[0]);
        }else{
            //Get user and guild data
            $user_id = $this->discord->users->get('id', $fromID);
            $guild_id = $this->discord->guilds->get('id', $guildID);
        }            

        //Check if guild is valid
        if(!$guild_id){
            //Guild not found
            $this->command_response('error', 'Set Notification', "The **Server ID** you specified was not found.", $reply_to);
            return false;
        }
        
        //Check for no arguments
        if(empty($arguments)){
            //Clear notifications for user
            $this->cache->remove('notifications', $fromID);
            
            //Send response
            $this->command_response('success', 'Set Notification', "Removed notification for {$user_id->username} on guild {$guild_id->name}.", $reply_to);
            return false;
        }
        
        //Check if user id is already in list
        if(!$this->cache->search([$fromID, $guild_id->id], 'notifications')){
            //User ID not in cache, create new
            if($this->cache->add('notifications', $fromID, $guild_id->id, json_encode($arguments))){
                //Success
                $this->command_response('success', 'Set Notification', "Added notification for {$user_id->username} on guild {$guild_id->name}.", $reply_to);
                return false;
            }else{
                //Failed
                $this->command_response('error', 'Set Notification', "Failed to add notification for {$user_id->username} on guild {$guild_id->name}.", $reply_to);
                return false;
            }
        }else{
            //User ID already in cache, remove
            $this->cache->remove('notifications', $fromID);
            
            //Add new notifications
            $this->cache->add('notifications', $fromID, $guild_id->id, json_encode($arguments));
        }
    }

    //List users inactive in x days
    private function admin_listinactive($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if guild id is set
        if(!$guildID){
            //Guild not found
            $this->command_response('error', 'List Inactive Users', "You cannot use this command in PM, please run this from the admin channel of a server.", $reply_to);
            return false;
        }
        
        //Check if amount of days is set
        if(!$arguments[0]){
            //No days passed
            $this->command_response('error', 'List Inactive Users', "You did not specify an amount of days.", $reply_to);
            return false;
        }
        
        //Get users for guild
        $guild = $this->discord->guilds->get('id', $guildID);
        $members = $guild->members;
        $timer = 0;
        
        //Loop through users in guild
        foreach($members as $member){
            //Calculate date difference
            $joined_date = strtotime($member->joined_at->setTimezone('America/New_York')->toDateTimeString());
            $inactive_date = (time() - $arguments[0] * 86400);
            
            //Get user activity
            $activity = get_last_seen($member->user->id);
            if($activity){
                $last_active = strtotime($activity['last_spoke']);
            }
            
            //Calculate days since user was last active
            if($last_active < $inactive_date){
                //User falls into inactive threshold, start array
                $fields = array();
                
                //Check username and ID
                $fields[] = ['name' => 'Username', 'value' => "{$member->user->username}", 'inline' => true];
                $fields[] = ['name' => 'ID', 'value' => "{$member->user->id}", 'inline' => true];

                //Check last time user spoke
                if(!$activity['last_spoke']){
                    $fields[] = ['name' => 'Last Spoke', 'value' => "Not tracked", 'inline' => true];
                }else{
                    $fields[] = ['name' => 'Last Spoke', 'value' => date('M d Y h:i A', strtotime($activity['last_spoke'])), 'inline' => true];
                }

                //Build embed
                $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                    'title' => ':information_source: User Info',
                    'timestamp' => false,
                    'fields' => $fields,
                    'thumbnail' => $this->discord->factory(Discord\Parts\Embed\Image::class, [
                        'url' => $member->user->avatar
                    ]),
                ]);
                
                //Set timer
                $this->discord->loop->addTimer($timer, function () use ($reply_to,$embed){
                    //Send embed message
                    send_embed_message($reply_to, $embed);
                });
                
                //Increase delay
                $timer = $timer +2;
            }
        }
    }

    //Set a role (or role group) on a user
    private function admin_setrole($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check if guild id is set
        if(is_countable($arguments) && count($arguments) > 1){
            //Attempt to get user
            $guild = $this->discord->guilds->get('id', $guildID);
            $member = $guild->members->get('id', $arguments[0]);
            
            //Remove user ID from arguments and create new argument var
            array_splice($arguments, 0, 1);
            $msgarg = implode(" ", $arguments);
        }else{
            //Get user and guild data
            $member = $this->discord->users->get('id', $fromID);
            $guild = $this->discord->guilds->get('id', $guildID);
        }

        //Check that a valid user was found
        if(!$member){
            //Invalid user, send message
            $this->command_response('error', 'Set Role', "The **User ID** you specified was not found.", $reply_to);
            return false;
        }

        //Check for role name
        if($msgarg == null) {
            //No role given, send message
            $this->command_response('error', 'Set Role', "No role or role group given.", $reply_to);
            return false;
        }
        
        //Role name given, attempt to get role from role name
        try{
            $role = $guild->roles->get('name', $msgarg);
        }catch(Exception $e){
            //Error getting role
            $role = false;
        }

        //Check that a valid role was found
        if(!$role){
            //No role found, look up as role group
            if($this->f3->get('roles_alias.'.$msgarg)){
                //Role group found, set
                $rolegroup = $this->f3->get('roles_alias.'.$msgarg);
            }else{
                //No role or role group matched
                $rolegroup = false;
            }
        }
        
        //Check that a valid role group was found
        if(!$role && !$rolegroup){
            //Invalid role, send message
            $this->command_response('error', 'Set Role', "The role or role group you specified was not found.", $reply_to);
            return false;
        }
        
        //Check if single role or role group
        if($role){
            //Single role, add role to member
            if($member->addRole($role->id)) {
                 $guild->members->save($member)->then(function () use ($guild, $member, $role){
                      $this->logger->notice("SET ROLE: Successfully set role {$role->name} for {$member->username} in {$guild->name}.");
                 }, function ($e){
                      $this->logger->warn("SET ROLE: Error setting role {$role->name} for {$member->username} in {$guild->name}: {$e->getMessage()}");
                 });
            }else{
                 //Member already has the role
                 $this->logger->warn("SET ROLE: Skipped setting role {$role->name} for {$member->username} in {$guild->name}, user already has role.");
            }
            
            //Success, send message
            $this->command_response('success', 'Set Role', "You have added the role {$role->name} to {$member->username}.", $reply_to);
            return false;
        }else{
            //Role group, check if any roles should be added
            if(is_countable($rolegroup['add']) && count($rolegroup['add']) > 0){
                //Loop through roles to be added
                foreach($rolegroup['add'] as $role_add){
                    //Get role from ID
                    $role = $guild->roles->get('id', $role_add);
                    
                    //Add role to user
                    if($member->addRole($role->id)) {
                         $guild->members->save($member)->then(function () use ($guild, $member, $role){
                              $this->logger->notice("SET ROLE GROUP: Successfully added role {$role->name} for {$member->username} in {$guild->name}.");
                         }, function ($e){
                              $this->logger->warn("SET ROLE GROUP: Error adding role {$role->name} for {$member->username} in {$guild->name}: {$e->getMessage()}");
                         });
                    }else{
                         //Member already has the role
                         $this->logger->warn("SET ROLE GROUP: Skipped adding role {$role->name} for {$member->username} in {$guild->name}, user already has role.");
                    }
                }
            }

            //Role group, check if any roles should be removed
            if(is_countable($rolegroup['remove']) && count($rolegroup['remove']) > 0){
                //Loop through roles to be added
                foreach($rolegroup['remove'] as $role_remove){
                    //Get role from ID
                    $role = $guild->roles->get('id', $role_remove);
                    
                    //Add role to user
                    if($member->removeRole($role->id)) {
                         $guild->members->save($member)->then(function () use ($guild, $member, $role){
                              $this->logger->notice("SET ROLE GROUP: Successfully removed role {$role->name} for {$member->username} in {$guild->name}.");
                         }, function ($e){
                              $this->logger->warn("SET ROLE GROUP: Error removing role {$role->name} for {$member->username} in {$guild->name}: {$e->getMessage()}");
                         });
                    }else{
                         //Member already has the role
                         $this->logger->warn("SET ROLE GROUP: Skipped removing role {$role->name} for {$member->username} in {$guild->name}, user does not have role.");
                    }
                }
            }
            
            //Success, send message
            $this->command_response('success', 'Set Role', "You have set the role group {$msgarg} on {$member->username}.", $reply_to);
            return false;
        }
    }

    //Allow user to opt in/out of guild notifications
    private function admin_notifyme($guildName, $channelID, $fromID, $command, $arguments, $msgarg, $guildID, $reply_to){
        //Check for a valid guild
        if($guildID == null){
            //Not a valid guild
            return false;
        }

        //Check if user specified to turn notifications on
        if($msgarg == 'on'){
            //User wants to opt in to notifications, check if they already are
            if(!$this->cache->search([$guildID, $fromID], 'notifyme')){
                //User is not opted in to notifications, add them
                if($this->cache->add('notifyme', $guildID, $fromID)){
                    //Success
                    $success = true;
                }else{
                    //Failed
                    $success = false;
                }
            }else{
                //User is already opted in to notifications
                $success = false;
            }
            
            //Check if user was opted in
            if($success){
                //Success
                $this->command_response('success', 'Notify Me [Opt-In]', "You have opted into join/leave/ban notifications for the server, you will now receive a DM when either of these events happen.", $reply_to);
                return false;
            }else{
                //Failed
                $this->command_response('error', 'Notify Me [Opt-In]', "You could not be opted into join/leave/ban notifications for the server, you may already be opted in, or there was an error.", $reply_to);
                return false;
            }
        }

        //Check if user specified to turn notifications off
        if($msgarg == 'off'){
            //User wants to opt out of notifications, check that they already are opted in
            if($this->cache->search([$guildID, $fromID], 'notifyme')){
                //User is opted in to notifications, remove them
                if($this->cache->remove('notifyme', $guildID, $fromID)){
                    //Success
                    $success = true;
                }else{
                    //Failed
                    $success = false;
                }
            }else{
                //User is already opted out of notifications
                $success = false;
            }
            
            //Check if user was opted out
            if($success){
                //Success
                $this->command_response('success', 'Notify Me [Opt-Out]', "You have opted out of join/leave/ban notifications for the server, you will not receive a DM when either of these events happen.", $reply_to);
                return false;
            }else{
                //Failed
                $this->command_response('error', 'Notify Me [Opt-Out]', "You could not be opted out of join/leave/ban notifications for the server, you may already be opted out, or there was an error.", $reply_to);
                return false;
            }
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "setgame";
        $commands[0]["trigger"] = array("!setgame");
        $commands[0]["information"] = "Sets current game for bot.\r\n**Usage:** !setgame *[game name]*";
        $commands[0]["admin_command"] = 1;

        $commands[1]["name"] = "listguilds";
        $commands[1]["trigger"] = array("!listguilds");
        $commands[1]["information"] = "Lists all Guild names and IDs the bot is in.\r\n**Note:** This command has no arguments.";
        $commands[1]["admin_command"] = 1;

        $commands[2]["name"] = "listchannels";
        $commands[2]["trigger"] = array("!listchannels");
        $commands[2]["information"] = "Lists all Channel names and IDs for specified guild ID.\r\n**Usage:** !listchannels *[guild id]*\r\n**Note:** If guild ID is not specified, it automatically gets set as the server you sent the command on.";
        $commands[2]["admin_command"] = 1;

        $commands[3]["name"] = "listusers";
        $commands[3]["trigger"] = array("!listusers");
        $commands[3]["information"] = "Lists all User names and IDs for specified guild ID.\r\n**Usage:** !listusers *[guild id]*\r\n**Note:** If guild ID is not specified, it automatically gets set as the server you sent the command on.";
        $commands[3]["admin_command"] = 1;

        $commands[4]["name"] = "ban";
        $commands[4]["trigger"] = array("!ban");
        $commands[4]["information"] = "Bans User ID from specified guild ID.\r\n**Usage:** !ban *[guild id]* *[user id]*\r\n**Note:** If guild ID is not specified, it automatically gets set as the server you sent the command on.";
        $commands[4]["admin_command"] = 1;

        $commands[5]["name"] = "kick";
        $commands[5]["trigger"] = array("!kick");
        $commands[5]["information"] = "Kicks User ID from specified guild ID.\r\n**Usage:** !kick *[guild id]* *[user id]*\r\n**Note:** If guild ID is not specified, it automatically gets set as the server you sent the command on.";
        $commands[5]["admin_command"] = 1;

        $commands[6]["name"] = "sendas";
        $commands[6]["trigger"] = array("!sendas");
        $commands[6]["information"] = "Sends message as bot to specified channel ID.\r\n**Usage:** !sendas *[channel id]*";
        $commands[6]["admin_command"] = 1;

        $commands[7]["name"] = "clear";
        $commands[7]["trigger"] = array("!clear");
        $commands[7]["information"] = "Clears last x number of chat messages for specified channel ID.\r\n**Usage:** !clear *[channel id]* *[# of messages]*\r\n**Note:** If channel ID is not specified, it automatically gets set as the channel you sent the command on.";
        $commands[7]["admin_command"] = 1;

        $commands[8]["name"] = "finduser";
        $commands[8]["trigger"] = array("!finduser");
        $commands[8]["information"] = "Search for user(s) matching specified text.\r\n**Usage:** !finduser *[part of name]*\r\n";
        $commands[8]["admin_command"] = 1;

        $commands[9]["name"] = "blacklist";
        $commands[9]["trigger"] = array("!blacklist");
        $commands[9]["information"] = "Blacklist channel from any logs.\r\n**Usage:** !blacklist *[channel id]*\r\n";
        $commands[9]["admin_command"] = 1;
        
        $commands[10]["name"] = "unblacklist";
        $commands[10]["trigger"] = array("!unblacklist");
        $commands[10]["information"] = "Un-blacklist channel from any logs.\r\n**Usage:** !unblacklist *[channel id]*\r\n";
        $commands[10]["admin_command"] = 1;

        $commands[11]["name"] = "addadmin";
        $commands[11]["trigger"] = array("!addadmin");
        $commands[11]["information"] = "Add userid to admin list.\r\n**Usage:** !addadmin *[user id]*\r\n";
        $commands[11]["admin_command"] = 1;
        
        $commands[12]["name"] = "removeadmin";
        $commands[12]["trigger"] = array("!removeadmin");
        $commands[12]["information"] = "Remove userid from admin list.\r\n**Usage:** !removeadmin *[user id]*\r\n";
        $commands[12]["admin_command"] = 1;

        $commands[13]["name"] = "getavatar";
        $commands[13]["trigger"] = array("!getavatar");
        $commands[13]["information"] = "Returns user's full size avatar.\r\n**Usage:** !getavatar *[user id]*\r\n";
        $commands[13]["admin_command"] = 1;

        $commands[14]["name"] = "addadminchan";
        $commands[14]["trigger"] = array("!addadminchan");
        $commands[14]["information"] = "Add admin channel for guild to receive update notifications.\r\n**Usage:** !addadminchan *[channel id]*\r\n";
        $commands[14]["admin_command"] = 1;
        
        $commands[15]["name"] = "removeadminchan";
        $commands[15]["trigger"] = array("!removeadminchan");
        $commands[15]["information"] = "Remove admin channel for guild to receive update notifications.\r\n**Usage:** !removeadminchan *[channel id]*\r\n";
        $commands[15]["admin_command"] = 1;

        $commands[16]["name"] = "setnotification";
        $commands[16]["trigger"] = array("!setnotification");
        $commands[16]["information"] = "Add a word list or phrase to notify user on, will send a message to the specified user every time a word or phrase in this list is said in any channel for guild.\r\n**Usage:** !setnotification *[user id]* *[word 1]* *[phrase 2]* *[word 3]*\r\n";
        $commands[16]["admin_command"] = 1;

        $commands[17]["name"] = "multiban";
        $commands[17]["trigger"] = array("!multiban");
        $commands[17]["information"] = "Bans list of User IDs from current guild.\r\n**Usage:** !multiban *[user id 1]* *[user id 2]* *[user id 3]*\r\n";
        $commands[17]["admin_command"] = 1;

        $commands[19]["name"] = "listinactive";
        $commands[19]["trigger"] = array("!listinactive");
        $commands[19]["information"] = "Lists users inactive for x days.\r\n**Usage:** !listinactive [# days]*\r\n";
        $commands[19]["admin_command"] = 1;

        $commands[20]["name"] = "getuserinfo";
        $commands[20]["trigger"] = array("!getuserinfo");
        $commands[20]["information"] = "Gets info on a user by ID.\r\n**Usage:** !getuserinfo *[user id]*\r\n";
        $commands[20]["admin_command"] = 1;
        
        $commands[21]["name"] = "setrole";
        $commands[21]["trigger"] = array("!setrole");
        $commands[21]["information"] = "Sets a role (or role group) on a user.\r\n**Usage:** !setrole *[user id]* *role name*\r\n";
        $commands[21]["admin_command"] = 1;

        $commands[22]["name"] = "notifyme";
        $commands[22]["trigger"] = array("!notifyme");
        $commands[22]["information"] = "Opt in or out to server join/leave/ban notifications in DM.\r\n**Usage:** !notifyme *on/off*\r\n";
        $commands[22]["admin_command"] = 1;

        return $commands;
    }
}