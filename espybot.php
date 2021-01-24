<?php
//Load F3 framework
$f3=require('./vendor/bcosca/fatfree-core/base.php');
$f3 = Base::instance();

//Load Discord
use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\WebSockets\WebSocket;
use Logger\Botlog;

//Allow up to 1GB memory usage
ini_set("memory_limit", "1024M");

//Set default timezone
date_default_timezone_set('America/New_York');

//Define the current dir
define("BASEDIR", __DIR__);
define("PLUGINDIR", __DIR__ . "/plugins/");

//In case we started from a different directory
chdir(BASEDIR);

//Bot start time
$startTime = time();

//Load all the vendor files
require_once(BASEDIR . "/vendor/autoload.php");

//Load configuration
$f3->config('config/config.ini');

//Load logger
require_once(__DIR__ . "/classes/log.php");
$logger = new Botlog();

//Startup Discord lib
$discord = new Discord([
    'token' => $f3->get('token'), 
    'loadAllMembers' => true,
]);

//Load the library files
foreach (glob(__DIR__ . "/library/*.php") as $lib){
    require_once($lib);
    $logger->debug("Loaded library: ".str_replace(".php", "", basename($lib)));
}

//Start the cacher
$cache = new prefcache();
$discord->logger = $logger;

//Load the plugins
$plugins = array();
foreach (glob(__DIR__ . "/plugins/*.php") as $plugin) {
    $fileName = str_replace(".php", "", basename($plugin));
    if($f3->get('plugins.'.$fileName)){
        require_once($plugin);
        $p = new $fileName();
        $p->init($f3, $discord, $logger);
        $plugins[] = $p;
        $logger->debug("Initialized plugin: {$fileName}");
    }
}

//Build command list
$commands = array();
$plugin_cmds = array();
foreach ($plugins as $plugin) {
    $infoarr = $plugin->information();
    if(is_countable($infoarr) && count($infoarr) !== 0){
        foreach($infoarr as $info){
            if ($info["name"]){
                $commands[] = $f3->get('trigger').$info["name"];
                $plugin_cmds[$info["name"]] = $plugin;
            }
        }
    }
}

//Show in use memory
$discord->loop->addPeriodicTimer(1800, function () use ($logger) {
    $logger->info("Memory in use: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB");
});

//Setup websocket hooks
$discord->on("ready", function() use ($discord, $logger, $plugins) {
    $logger->notice("Connection Opened");
});

$discord->on('reconnecting', function () use ($logger) { 
    $logger->err('Discord WebSocket is reconnecting...');
});

$discord->on('reconnected', function () use ($logger) { 
    $logger->err('Discord WebSocket has reconnected.');
});

$discord->on('close', function ($op, $reason) use ($logger) {
    $logger->err("Discord WebSocket closed with close code {$op} reason {$reason}");
});

$discord->on('error', function ($e) use ($logger) {
    $logger->err('Discord WebSocket encountered an error.', [$e]);
});

//On a message, do all of the following
$discord->on(Event::MESSAGE_CREATE, function ($msgData, $botData) use ($logger, $discord, $plugins, $f3, $commands, $plugin_cmds){
    //If we sent the message, just ignore it
    if($msgData->author->id != $discord->id) {
        //Check for command, so we don't do all of this expensive shit for a random message
        if ($command = containsTrigger($msgData->content, $commands)) {
            $channelData = $msgData->channel;

            //Log command
            $logger->debug("Command processed -> ".$msgData->content);
            
            //Store message object
            $msgobject = $msgData;

            //If PM, set channel name to username sending message
            if($channelData->is_private == true)
                $channelData->setAttribute("name", $msgData->author->username);

            //Create the data array for the plugins to use
            $msgData = array(
                "isBotOwner" => false,
                "user" => $msgData->author,
                "message" => array(
                    "timestamp" => $msgData->timestamp->setTimezone('America/New_York')->toDateTimeString(),
                    "id" => $msgData->author->id,
                    "message" => $msgData->content,
                    "channelID" => $msgData->channel_id,
                    "from" => $msgData->author->username,
                    "fromID" => $msgData->author->id,
                    "fromDiscriminator" => $msgData->author->discriminator,
                    "fromAvatar" => $msgData->author->avatar,
                    "attachments" => $msgData->attachments
                ),
                "channel" => $channelData,
                "guild" => $channelData->is_private ? array("name" => "PM") : array("id" => $channelData->guild_id, "name" => $channelData->guild->name),
                "object" => $msgobject
            );

            //Fire main function for public command
            try{
                $plugin_cmds[$command]->onMessage($msgData);
            }catch (\Exception $e){
                $logger->err("Command: ".$e->getMessage());
            }


            //Check if user is an admin or bot owner
            if(is_admin($msgData["message"]["fromID"]) || is_bot_owner($msgData["message"]["fromID"])){
                //Fire main function for admin command
                try{
                    $plugin_cmds[$command]->onMessageAdmin($msgData);
                }catch (\Exception $e){
                    $logger->err("Admin Command: ".$e->getMessage());
                }
            }
        }else{
            //This is not a command, process for logging
            //dump($msgData->channel);
            $channelData = $msgData->channel;

            //If PM, set channel name to username sending message
            if($channelData->is_private == true)
                $channelData->setAttribute("name", $msgData->author->username);

            //Create the data array for the plugins to use
            $msgData = array(
                "isBotOwner" => false,
                "user" => $msgData,
                "message" => array(
                    "timestamp" => $msgData->timestamp->setTimezone('America/New_York')->toDateTimeString(),
                    "id" => $msgData->author->id,
                    "message" => $msgData->content,
                    "channelID" => $msgData->channel_id,
                    "from" => $msgData->author->username,
                    "fromID" => $msgData->author->id,
                    "fromDiscriminator" => $msgData->author->discriminator,
                    "fromAvatar" => $msgData->author->avatar,
                    "attachments" => $msgData->attachments
                ),
                "channel" => $channelData,
                "guild" => $channelData->is_private ? array("name" => "PM") : array("id" => $channelData->guild_id, "name" => $channelData->guild->name),
                "botData" => $botData
            );
            
            //Run the plugins log functions
            foreach($plugins as $plugin){
                try{
                    $plugin->onLog($msgData);
                }catch (\Exception $e){
                    $logger->err("Log Error: " . $e->getMessage());
                }   
            }
        }
    }
});

//Start the bot
$discord->run();