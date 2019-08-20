<?php

class reply{
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
        $this->cleverbot_client = new \GuzzleHttp\Client();
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
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        //Set the guild ID for server or PM
        if ($msgData["guild"]["id"] !== null){
            $guildID = $msgData["guild"]["id"];
        }else{
            $guildID = null;
        }
        //Check if the chat message contains the fucking trigger
        if(strpos($message,"<@".$this->discord->id.">") !== false || stripos($message,$this->discord->username) !== false){
            $this->get_response($message, false, $guildID, $fromID)
                ->then(function ($reply) use ($message, $channelName, $guildName, $channelID, $fromID, $guildID) {
                    send_message($channelID,$fromID,$reply);
                })
                ->otherwise(function ($error) use ($message, $channelName, $guildName, $channelID, $fromID, $guildI){
                    $this->logger->debug("REPLY - Unhandled Error in response: {$error}");
                });
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

    function get_response($message, $last_response, $guildID = null, $fromID = null){
        //Generate random number
        $rand = mt_rand();

        //Start promise
        $deferred = new \React\Promise\Deferred();
        
        //Check if we got a valid response last loop
        if ($last_response){
            //Exit loop, last response was good
            return new \React\Promise\FulfilledPromise($last_response);
        }

        //Get random function name
        $functions = preg_grep('/^(?!get).+_response$/', get_class_methods($this));
        $random_func = $functions[array_rand($functions)];
        
        //Run random function
        $this->$random_func($guildID, $fromID, $message)
            ->then(function ($response) use ($message, $last_response, $deferred, $guildID, $fromID) {
                //Store response as last response and send it through the next loop
                $last_response = $response;
                //Loop through get_response again
                $this->get_response($message, $last_response, $guildID, $fromID)
                    ->then(function ($response) use ($message, $last_response, $deferred, $guildID, $fromID){
                        //Resolve promise, send response
                        $deferred->resolve($response);
                    })
                    ->otherwise(function ($error) use ($message, $last_response, $deferred, $guildID, $fromID){
                        //Resolve promise, send error
                        $deferred->reject($error);
                    });
                })
            ->otherwise(function ($error) use ($message, $last_response, $deferred, $guildID, $fromID){
                //Since we got an error, try again
                $last_response = null;
                //Loop through get_response again
                $this->get_response($message, $last_response, $guildID, $fromID)
                    ->then(function ($response) use ($message, $last_response, $deferred, $guildID, $fromID){
                        //Resolve promise, send response
                        $deferred->resolve($response);
                    })
                    ->otherwise(function ($error) use ($message, $last_response, $deferred, $guildID, $fromID){
                        //Resolve promise, send error
                        $deferred->reject($error);
                    });
            });

        //Return promise
        return $deferred->promise();
    }

    function cleverio_response($guildID, $fromID, $message){
        //Start promise
        $deferred = new \React\Promise\Deferred();
        
        //Remove bot name and empty spaces
        $message = str_ireplace($this->discord->username, "Jim", $message);
        $message = str_ireplace("<@".$this->discord->id.">", "Jim", $message);
        $message = preg_replace('!\s+!', ' ', $message);
        $message = ltrim($message);

        //Also change any metions into a simple name
        $message = preg_replace("/<@\d+?>/","Jim",$message);
        $message = preg_replace('/<(\@[^\d]\w+)>/',"Jim",$message);
        
        $this->logger->debug("REPLY - Message sent to Cleverbot: {$message}");
        
        try{
            //Send API request to cleverbot.io
            $response = $this->cleverbot_client->request('POST', 'https://cleverbot.io/1.0/ask', [
                'connect_timeout' => 2,
                'form_params' => [
                    'user' => $this->f3->get("cleverio_user"),
                    'key' => $this->f3->get("cleverio_key"),
                    'nick' => $this->f3->get("cleverio_nick"),
                    'text' => $message
                ]
            ]);
        }catch(Exception $e){
            //Bad response from server
            $this->logger->err("REPLY - Error from Cleverbot: {$e->getMessage()}");
            $deferred->reject($e->getMessage());
        }

        //Parse JSON
        if($response){
            $body = (string)$response->getBody();
            $data = json_decode($body);
        }else{
            $data = null;
        }
        
        //Check if JSON is valid
        if($data->status == "success" && property_exists($data, 'response')){
            //Store response in promise
            $this->logger->debug("REPLY - Message recieved from Cleverbot: {$data->response}");
            $deferred->resolve($data->response);
        }else{
            //Server side error or parsing problem
            $deferred->reject('Server side error.');
        }

        //Return promise
        return $deferred->promise();
    }

    function cleverbot_response($guildID, $fromID, $message){
        //Start promise
        $deferred = new \React\Promise\Deferred();
        
        //Remove bot name and empty spaces
        $message = str_ireplace($this->discord->username, "Jim", $message);
        $message = str_ireplace("<@".$this->discord->id.">", "Jim", $message);
        $message = preg_replace('!\s+!', ' ', $message);
        $message = ltrim($message);

        //Also change any metions into a simple name
        $message = preg_replace("/<@\d+?>/","Jim",$message);
        $message = preg_replace('/<(\@[^\d]\w+)>/',"Jim",$message);
        
        $this->logger->debug("REPLY - Message sent to Cleverbot: {$message}");
        
        try{
            //Send API request to cleverbot
            $response = $this->cleverbot_client->request('GET', 'https://www.cleverbot.com/getreply?key='.$this->f3->get('cleverbot_key'), [
                'connect_timeout' => 2,
            ]);
        }catch(Exception $e){
            //Bad response from server
            $this->logger->err("REPLY - Error from Cleverbot: {$e->getMessage()}");
            $deferred->reject($e->getMessage());
        }

        //Parse JSON
        if($response){
            $body = (string)$response->getBody();
            $data = json_decode($body);
        }else{
            $data = null;
        }
        
        //Check if JSON is valid
        if($data->output != "" && property_exists($data, 'output')){
            //Store response in promise
            $this->logger->debug("REPLY - Message recieved from Cleverbot: {$data->output}");
            $deferred->resolve($data->output);
        }else{
            //Server side error or parsing problem
            $deferred->reject('Server side error.');
        }

        //Return promise
        return $deferred->promise();
    }
    
    function stored_response($guildID, $fromID, $message){
        $this->logger->debug("REPLY - Stored response Guild ID: {$guildID}");
        //Include database
        global $db;

        //Start promise
        $deferred = new \React\Promise\Deferred();
        
        //There are channels in the blacklist, setup the DB Mapper
        $db_map = new DB\SQL\Mapper($db,'logs');
        
        //Set params array
        $params = array();
        
        //Check if guild ID is defined, or PM
        if($guildID != null){
            //Add guild ID and bot ID to array
            $params[] = $guildID;
            $params[] = $this->f3->get('instance');
            $params[] = $guildID;
            $params[] = $this->f3->get('instance');
        }else{
            //Add bot ID to array
            $params[] = $this->f3->get('instance');
            $params[] = $this->f3->get('instance');
        }
        
        //Set query options
        $options = [
    	    'order' => 'RAND()',
    	    'limit' => 1,
	    ];
        
        try{
            //Check if guild ID is defined, or PM
            if($guildID != null){
                //Query for message from guild
                $msg = $db_map->find(["channel NOT IN (SELECT value1 FROM config WHERE `key` = 'blacklist' AND value2 = ? AND bot_id = ?) AND guild = ? AND bot_id = ?", $params], $options);
            }else{
                //Query for message from all guilds
                $msg = $db_map->find(["channel NOT IN (SELECT value1 FROM config WHERE `key` = 'blacklist' AND bot_id = ?) AND bot_id = ?", $params], $options);
            }
        }catch(Exception $e){
            //Database error
            $this->logger->err("REPLY - Error from DB: {$e->getMessage()}");
            $deferred->reject($e->getMessage());
        }
        
        //Replace bot name with userid, do word replacements and replace and userids because people got their tits in a bunch about it
        $msgcontent = str_ireplace($this->discord->username, "<@$fromID>", $msg[0]["message"]);
        
        //$msgcontent = str_ireplace($wordblacklist, $wordreplace, $msgcontent);
        $msgcontent = preg_replace("/<@\d+?>/","<@$fromID>",$msgcontent);
        $msgcontent = preg_replace('/<(\@[^\d]\w+)>/',"<@$fromID>",$msgcontent);
        
        //Make sure by the time we replace shit, that the message is not empty
        if ($msgcontent == ""){
            //Send that bitch back in the loop
            $this->logger->debug("REPLY - Message picked was null after replacements.");
            //Message is empty
            $deferred->reject('Empty message after replacements.');
        }else{
            //We can exit out this bitch
            $this->logger->debug("REPLY - Message recieved from DB: {$msgcontent}");
            $deferred->resolve($msgcontent);
        }
        
        //Return promise
        return $deferred->promise();
    }
}
