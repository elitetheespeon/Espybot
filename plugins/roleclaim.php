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

    /**
     * @param $msgData
     */
    function onLog($msgData){
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        //Set the guild ID for server or PM
        if ($msgData["guild"]["id"] !== null){
            $guildID = $msgData["guild"]["id"];
        }else{
            $guildID = null;
        }
        //stuff
    }
    
    function reaction_add($msgData){
        global $f3, $discord, $logger;
        
        //Loop through list of reactions
        foreach($f3->get('roleclaim') as $role){
            //Check if reaction matches the saved emoji
            if($role['emoji'] == $msgData->emoji->name){
                //Get the member data
                $guild = $discord->guilds->get('id', $msgData->guild_id);
                $member = $guild->members->get('id', $msgData->user_id);
                
                //Give the role to the user
                $member->addRole($role['role_id'])
                ->then(function () use ($guild, $member, $logger, $role){
                     $logger->notice("GIVE ROLE: Successfully added role {$role['desc']} for {$member->username} in {$guild->name}.");
                })
                ->otherwise(function ($e) use ($guild, $member, $logger, $role){
                     //Member already has the role
                     $logger->warn("GIVE ROLE: Skipped adding role {$role['desc']} for {$member->username} in {$guild->name}, user already has role.");
                });
            }
        }
    }
    
    function reaction_remove($msgData){
        global $f3, $discord, $logger;
        
        //Loop through list of reactions
        foreach($f3->get('roleclaim') as $role){
            //Check if reaction matches the saved emoji
            if($role['emoji'] == $msgData->emoji->name){
                //Get the member data
                $guild = $discord->guilds->get('id', $msgData->guild_id);
                $member = $guild->members->get('id', $msgData->user_id);
                
                //Give the role to the user
                $member->removeRole($role['role_id'])
                ->then(function () use ($guild, $member, $logger, $role){
                     $logger->notice("REMOVE ROLE: Successfully removed role {$role['desc']} for {$member->username} in {$guild->name}.");
                })
                ->otherwise(function ($e) use ($guild, $member, $logger, $role){
                     //Member already has the role
                     $logger->warn("REMOVE ROLE: Skipped adding role {$role['desc']} for {$member->username} in {$guild->name}, user already has role.");
                });
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