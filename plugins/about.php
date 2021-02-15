<?php

class about{
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
        //Get start time of bot
        global $startTime;
        
        //Compare start time against time now
        $time1 = new DateTime(date("Y-m-d H:i:s", $startTime));
        $time2 = new DateTime(date("Y-m-d H:i:s"));
        $interval = $time1->diff($time2);

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
        
        //Format time
        $timestring  = "";
        if($interval->y != null){
            $timestring .= "{$interval->y} Year(s), ";
        }
        if($interval->m != null){
            $timestring .= "{$interval->m} Month(s), ";
        }
        if($interval->d != null){
            $timestring .= "{$interval->d} Days, ";
        }
        if($interval->h != null){
            $timestring .= "{$interval->h} Hours, ";
        }
        if($interval->i != null){
            $timestring .= "{$interval->i} Minutes, ";
        }
        if($interval->s != null){
            $timestring .= "{$interval->s} Seconds";
        }

        //Build embed
        $embed = $this->discord->factory(\Discord\Parts\Embed\Embed::class, [
            'title' => ':information_source: Bot info',
            'description' => "I am {$this->discord->username}, a bot with useful moderation/administration functions, as well as fun commands!",
            'timestamp' => false,
            'fields' => [
                ['name' => 'Bot Author', 'value' => "https://eevee.facewan.com/forums/members/l337espeon.1/", 'inline' => true],
                ['name' => 'Uptime', 'value' => "{$timestring}", 'inline' => true],
                ['name' => 'Server Count', 'value' => (string)count($this->discord->guilds), 'inline' => true],
                ['name' => 'User Count', 'value' => (string)count($this->discord->users), 'inline' => true],
                ['name' => 'Memory Usage', 'value' => round(memory_get_usage() / 1024 / 1024, 2)." MB", 'inline' => true],
                ['name' => 'Git revision', 'value' => substr(file_get_contents('.git/refs/heads/master'), 0, 9), 'inline' => true],
                ['name' => 'DiscordPHP revision', 'value' => substr(file_get_contents('vendor/team-reflex/discord-php/.git/HEAD'), 0, 9), 'inline' => true],
            ],
        ]);

        //Send message to chat
        send_embed_message($reply_to,$embed);

        //Log action
        if($channelName){
            $this->logger->info("Sending about info to {$channelName} on {$guildName}");
        }else{
            $this->logger->info("Sending about info to {$fromName} in PM");
        }
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "about";
        $commands[0]["trigger"] = array("!about");
        $commands[0]["information"] = "Shows information on the bot, who created it, what library it's using, revision, and other stats.\r\n**Usage:** !about\r\n**Note:** This command has no arguments.";
        $commands[0]["admin_command"] = 0;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }

}
