<?php

class pokedex{
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
     * @trivia vars
     */
    var $trivia_answer;
    var $trivia_scores;
    var $trivia_current;
    var $trivia_active;
    var $trivia_channel;
    var $trivia_has_answered;

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
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        $fromName = $msgData["message"]["from"];

        //Process trigger and resolve command/argument parts
        $command = processTrigger($message, get_plugin_commands($this));
        $arguments = $command['arguments'];
        $msgarg = $command['argument'];

        //Set the guild ID for server or PM
        if ($msgData["guild"]["id"] !== null){
            $guildID = $msgData["guild"]["id"];
        }else{
            $guildID = null;
        }
        
        //Check if trigger was processed
        if(isset($command['command'])){
            //Check trigger type
            switch ($command['command']){
                //Start trivia
                case "triviastart":
                    //Reset count to 0
                    $this->trivia_active = true;
                    $this->trivia_current = 0;
                    $this->trivia_scores = array();
                    $this->trivia_has_answered = array();
                    
                    //Build start/help message
                    $message = "Trivia started! Have the most points by the end of 20 questions and win. Answer a question by typing !answer **answer**.\r\n";
                    $message .= "Example: ***!answer cat*** would answer the question with ***cat***\r\n";
                    $message .= "**Currently trivia is only pokedex entries, guess that pokemon!**";
                    
                    //Send message
                    send_message($channelID,null,$message);
                    
                    //Save channel ID
                    $this->trivia_channel = $channelID;
                    
                    //Start first question
                    $this->trivia_question();
                    break;
                
                //Stop trivia
                case "triviastop":
                    //Save trivia channel
                    $trivchan = $this->trivia_channel;
                    
                    //Get winner and stop session
                    $this->get_winner();
                    $this->trivia_current = null;
                    $this->trivia_answer = null;
                    $this->trivia_scores = null;
                    $this->trivia_channel = null;
                    $this->trivia_active = false;
                    $this->trivia_has_answered = null;
                    
                    //Build end message
                    $message = "Trivia stopped! Hope you had fun!";
                    
                    //Send message
                    send_message($trivchan,null,$message);
                    break;
                
                //Answer trivia question
                case "answer":
                    //Check if trivia is active and answer is not null
                    if($arguments[0] !== null && $this->trivia_active == true && $this->trivia_channel == $channelID){
                        //Check answer, apply score
                        if($this->trivia_has_answered[$fromID] !== 1){
                            if($arguments[0] == $this->trivia_answer){
                                $this->logger->notice("TRIVIA - ".$fromName." score ".$this->trivia_scores[$fromID].".");
                                //Correct answer
                                if($this->trivia_scores[$fromID] == ""){
                                    $this->trivia_scores[$fromID] = 1;
                                    $this->trivia_has_answered[$fromID] = 1;
                                    $this->logger->notice("TRIVIA - ".$fromName." score set to 1.");
                                }else{
                                    $this->trivia_scores[$fromID]++;
                                    $this->trivia_has_answered[$fromID] = 1;
                                    $this->logger->notice("TRIVIA - ".$fromName." score went up 1.");
                                }
                            }else{
                                $this->trivia_has_answered[$fromID] = 1;
                            }
                            //Build reply message
                            $message = "Answer saved for ".$fromName."!";
                        }else{
                            //Build reply message
                            $message = $fromName.", you cannot change your answer!";                            
                        }
                        //Send message
                        send_message($channelID,null,$message);         
                    }
                    break;
            }
        }
    }

    function trivia_question(){
        //Check if 20 question limit has been hit
        if($this->trivia_current == 20){
            $this->logger->notice("TRIVIA - Session ended.");
            
            //Get winner and stop session
            $this->get_winner();
            $this->trivia_current = null;
            $this->trivia_answer = null;
            $this->trivia_scores = null;
            $this->trivia_active = false;
        }else{
            if($this->trivia_active == true){
                $this->logger->notice("TRIVIA - New question started.");
                
                //Increase question number
                $this->trivia_current++;
                
                //Clear answers
                $this->trivia_has_answered = null;
                
                //Get random pokemon number
                $poke_num = rand(1,721);
                
                //Make API call
                $client = new GuzzleHttp\Client();
                $body = $client->get('http://pokeapi.co/api/v2/pokemon-species/'.$poke_num.'/')->getBody();
                $return = json_decode($body);
                
                //Check if respone was valid
                if($return){
                    //Store pokemon name and pokedex entries
                    $poke_name = $return->name;
                    $poke_desc = $return->flavor_text_entries;
                    
                    //Make array of pokedex entries
                    $pokedexarr = array();
                    
                    //Sort through pokedex array - only save english ones
                    foreach($poke_desc as $poke){
                        if($poke->language->name == "en"){
                            $pokedexarr[] = $poke->flavor_text;
                        }
                    }
                    
                    $max_rand = count($pokedexarr);
                    
                    //Check for valid pokedex entry, loop through until get one
                    $validdex = false;
                    $try = 0;
                    while($validdex == false && $try < 10){
                        //Increase attempt number
                        $try++;
                        
                        //Grab a random pokedex entry
                        $rand_dex = rand(0,$max_rand-1);
                        $dex = $pokedexarr[$rand_dex];
                        
                        if(trim($dex) !== ""){
                            $validdex = true;
                        }
                    }
                    
                    //Add message with trivia info
                    $msgstart = "Question #".$this->trivia_current.": ";
                    
                    //Filter out pokemon name and any line breaks
                    $message = preg_replace('/('.$poke_name.')/i','**POKéMON**',$dex);
                    $message = preg_replace("/[\n\r]/"," ",$message);
                    var_dump($message);
                    
                    //Save pokemon name as answer
                    $this->trivia_answer = $poke_name;
                    
                    //Send message back to chat
                    send_message($this->trivia_channel,null,$msgstart.$message);
                    
                    //Set timer to time question
                    $this->discord->loop->addTimer(60, function () use ($logger){
                        $this->logger->notice("TRIVIA - Question time ended.");
                        //Build question ending message
                        $message = "Time is up for question #".$this->trivia_current."! Answer: **".$this->trivia_answer."**";
                        
                        //Send message
                        send_message($this->trivia_channel,null,$message);
                        
                        //Call this same function again
                        $this->trivia_question();
                    });
                }else{
                    //Invalid response, retry
                    $this->trivia_question();
                }
            }
        }
    }

    function get_winner(){
        if($this->trivia_scores){
            //Sort score array and get highest score
            arsort($this->trivia_scores);
            $highscore = current($this->trivia_scores);
            $winners = array();
            
            //Loop through each user's score
            foreach($this->trivia_scores as $winuser => $winscore){
                //Check if the user has the highest score
                if($winscore == $highscore){
                    //Add user to winner list
                    $winners[] = $winuser;
                }
            }
            //Check if winners exist
            if(!empty($winners)){
                //Check if there is more than 1 winner
                if(count($winners) == 1){
                    //Start winner message
                    $message = "The winner is:";
                    
                    //Resolve winner ID
                    $winner = $this->discord->users->get('id', $winners[0]);
                    
                    //Get winner name
                    if($winner){
                        $winner_name = $winner->username;
                    }else{
                        $winner_name = "[ERROR]";
                    }
                    
                    //Add winner name to message
                    $message .= " *".$winner_name."* ";
                }else{
                    //Start winner message
                    $message = "The winners are:";
                    
                    //Loop through winners
                    foreach($winners as $winner_id){
                        //Resolve winner ID
                        $winner = $this->discord->users->get('id', $winner_id);
                        
                        //Get winner name
                        if($winner){
                            $winner_name = $winner->username;
                        }else{
                            $winner_name = "[ERROR]";
                        }
                        
                        //Add winner name to message
                        $message .= " *".$winner_name."* ";
                    }
                }
                //End message
                $message .= "with ".$highscore." points!";
            }else{
                //Build winner message
                $message = "Nobody scored any points!";
            }
        }else{
            //Build winner message
            $message = "Nobody scored any points!";            
        }
        
        //Send message
        send_message($this->trivia_channel,null,$message);
    }

    /**
     * @return array
     */
    function information(){
        $commands[0]["name"] = "triviastart";
        $commands[0]["trigger"] = array("!triviastart");
        $commands[0]["information"] = "Starts a trivia session of 20 questions.\r\n**Usage:** !triviastart *[tags]*\r\n**Note:** This command can only be used certain channels.";
        $commands[0]["admin_command"] = 0;
        $commands[1]["name"] = "triviastop";
        $commands[1]["trigger"] = array("!triviastop");
        $commands[1]["information"] = "Stops a trivia session.\r\n**Usage:** !triviastop *[tags]*\r\n**Note:** This command can only be used certain channels.";
        $commands[1]["admin_command"] = 0;
        $commands[2]["name"] = "answer";
        $commands[2]["trigger"] = array("!answer");
        $commands[2]["information"] = "Answers a trivia question.\r\n**Usage:** !answer *[answer]*\r\n**Note:** This command can only be used certain channels.";
        $commands[2]["admin_command"] = 0;
        return $commands;
    }

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData){
    }
}
