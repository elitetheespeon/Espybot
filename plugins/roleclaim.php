<?php

class roleclaim{
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
        //Include cache
        global $cache;
        
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
                //Give public role to user
                case "giverole":
                    try{
                        //Look up role
                        $guild = $this->discord->guilds->get('id', $guildID);
                        $member = $guild->members->get('id', $fromID);
                        $role = $guild->roles->get('name', $msgarg);
                    }catch(Exception $e){
                        //Error
                        $role = null;
                        $allowed = false;
                    }
                    
                    //Check we have valid role
                    if($role !== null){
                        //Valid role found, check if role is public
                        if($cache->search([$guildID, $role->id], 'publicroles')){
                            //Role is public
                            $allowed = true;
                        }else{
                            //Role is not public
                            $allowed = false;
                        }
                        
                        //Check if user has access to role
                        if($allowed){
                            //User has access to role, add role to member
                            if($member->addRole($role->id)) {
                                 $guild->members->save($member)->then(function () use ($guild, $member){
                                      $this->logger->notice("GIVE ROLE: Successfully added role for {$member->username} in {$guild->name}.");
                                 }, function ($e){
                                      $this->logger->warn("GIVE ROLE: Error adding role for {$member->username} in {$guild->name}: {$e->getMessage()}");
                                 });
                            }else{
                                 //Member already has the role
                                 $this->logger->warn("GIVE ROLE: Skipped adding role for {$member->username} in {$guild->name}, user already has role.");
                            }
                            
                            //Build embed
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ':white_check_mark: Give Role',
                                'description' => "You have given the role {$role->name} to yourself.",
                                'timestamp' => false,
                            ]);
                        }else{
                            //User does not have access to role
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ':x: Give Role',
                                'description' => "The role you specified does not exist or is not public.",
                                'timestamp' => false,
                            ]);
                        }
                    }else{
                        //Role name was not found
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Give Role',
                            'description' => "The role you specified does not exist or is not public.",
                            'timestamp' => false,
                        ]);
                    }
                    
                    //Send message to chat
                    send_embed_message($reply_to, $embed);
                    break;
                    
                //Remove public role from user
                case "removerole":
                    try{
                        //Look up role
                        $guild = $this->discord->guilds->get('id', $guildID);
                        $member = $guild->members->get('id', $fromID);
                        $role = $guild->roles->get('name', $msgarg);
                    }catch(Exception $e){
                        //Error
                        $role = null;
                        $allowed = false;
                    }
                    
                    //Check we have valid role
                    if($role !== null){
                        //Valid role found, check if role is public
                        if($cache->search([$guildID, $role->id], 'publicroles')){
                            //Role is public
                            $allowed = true;
                        }else{
                            //Role is not public
                            $allowed = false;
                        }
                        
                        //Check if user has access to role
                        if($allowed){
                            //Remove role from member
                            if($member->removeRole($role->id)) {
                                 $guild->members->save($member)->then(function () use ($guild, $member){
                                      $this->logger->notice("REMOVE ROLE: Successfully removed role for {$member->username} in {$guild->name}.");
                                 }, function ($e){
                                      $this->logger->warn("REMOVE ROLE: Error removing role for {$member->username} in {$guild->name}: {$e->getMessage()}");
                                 });
                            }else{
                                 //Member already has the role
                                 $this->logger->warn("REMOVE ROLE: Skipped removing role for {$member->username} in {$guild->name}, user already has role.");
                            }
                            
                            //Build embed
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ':white_check_mark: Remove Role',
                                'description' => "You have removed the role {$role->name} from yourself.",
                                'timestamp' => false,
                            ]);
                        }else{
                            //User does not have access to role
                            $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                                'title' => ':x: Remove Role',
                                'description' => "The role you specified does not exist or is not public.",
                                'timestamp' => false,
                            ]);
                        }
                    }else{
                        //Role name was not found
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Remove Role',
                            'description' => "The role you specified does not exist or is not public.",
                            'timestamp' => false,
                        ]);
                    }
                    
                    //Send message to chat
                    send_embed_message($reply_to, $embed);
                    break;
            }
        }
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
        //Include cache
        global $cache;
        
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
                //Add public status to role
                case "addpublicrole":
                    try{
                        //Look up role
                        $guild = $this->discord->guilds->get('id', $guildID);
                        $member = $guild->members->get('id', $fromID);
                        $role = $guild->roles->get('name', $msgarg);
                    }catch(Exception $e){
                        //Error
                        $role = null;
                    }
                    
                    //Check we have valid role
                    if($role !== null){
                        //Valid role found, check if role is already public
                        if(!$cache->search([$guildID, $role->id], 'publicroles')){
                            //Role is not already public, add it
                            if($cache->add('publicroles', $guildID, $role->id)){
                                //Success
                                $success = true;
                            }else{
                                //Failed
                                $success = false;
                            }
                        }else{
                            //Role is already public
                            $success = false;
                        }
                    }else{
                        //Role does not exist
                        $success = false;
                    }

                    //Check if role was added
                    if($success){
                        //Build embed
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':white_check_mark: Add Public Role',
                            'description' => "The Role {$role->name} has been successfully added as a public role and users can now grant themselves this role.",
                            'timestamp' => false,
                        ]);
                    }else{
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Add Public Role',
                            'description' => "The role you specified does not exist or is already a public role.",
                            'timestamp' => false,
                        ]);
                    }

                    //Send message to chat
                    send_embed_message($reply_to, $embed);
                    break;
                
                //Remove public status from role
                case "removepublicrole":
                    try{
                        //Look up role
                        $guild = $this->discord->guilds->get('id', $guildID);
                        $member = $guild->members->get('id', $fromID);
                        $role = $guild->roles->get('name', $msgarg);
                    }catch(Exception $e){
                        //Error
                        $role = null;
                    }
                    
                    //Check we have valid role
                    if($role !== null){
                        //Valid role found, check if role is already public
                        if($cache->search([$guildID, $role->id], 'publicroles')){
                            //Role is public, remove it
                            if($cache->remove('publicroles', $guildID, $role->id)){
                                //Success
                                $success = true;
                            }else{
                                //Failed
                                $success = false;
                            }
                        }else{
                            //Role is not public
                            $success = false;
                        }
                    }else{
                        //Role does not exist
                        $success = false;
                    }

                    //Check if role was added
                    if($success){
                        //Build embed
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':white_check_mark: Remove Public Role',
                            'description' => "The Role {$role->name} has been successfully removed as a public role and users will not be able to grant themselves this role.",
                            'timestamp' => false,
                        ]);
                    }else{
                        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
                            'title' => ':x: Remove Public Role',
                            'description' => "The role you specified does not exist or is not a public role.",
                            'timestamp' => false,
                        ]);
                    }
                    
                    //Send embed to channel
                    send_embed_message($reply_to, $embed);
                    break;
            }
        }
    }
    
    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "giverole";
        $commands[0]["trigger"] = array("!giverole");
        $commands[0]["information"] = "Allows a user to add a public role to themselves.\r\n**Usage:** !giverole *[role name]*\r\n";
        $commands[0]["admin_command"] = 0;
        $commands[1]["name"] = "removerole";
        $commands[1]["trigger"] = array("!removerole");
        $commands[1]["information"] = "Allows a user to remove a public role from themselves.\r\n**Usage:** !removerole *[role name]*\r\n";
        $commands[1]["admin_command"] = 0;
        $commands[2]["name"] = "addpublicrole";
        $commands[2]["trigger"] = array("!addpublicrole");
        $commands[2]["information"] = "Add a role to be public so that users can add/remove the role on their own.\r\n**Usage:** !addpublicrole *[role name]*\r\n";
        $commands[2]["admin_command"] = 1;
        $commands[3]["name"] = "removepublicrole";
        $commands[3]["trigger"] = array("!removepublicrole");
        $commands[3]["information"] = "Removes a role from being public.\r\n**Usage:** !removepublicrole *[role name]*\r\n";
        $commands[3]["admin_command"] = 1;
        return $commands;
    }
}