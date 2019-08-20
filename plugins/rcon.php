<?php

class rcon{
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
        //Include source query and current server
        global $game_query, $curr_server;
        
        //Set local names for message parts
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];
        $fromID = $msgData["message"]["fromID"];
        $guildID = $msgData["guild"]["id"];
        
        //Loop through rcon channel IDs
        foreach($this->f3->get('gameserver_rcon_chan') as $rcon_channel){
            //Check if message was sent from the rcon channel
            if($channelID == $rcon_channel){
                //Set the triggers we accept commands for
                $cmd_arr = check_rcon_cmd($message, array('.help','.list','.goto','.quit'));
                
                //Split message string by spaces for arguments
                $arguments = explode(" ", $cmd_arr["messageString"]);
                
                //Check if the chat message contains the trigger
                if(isset($cmd_arr["trigger"])){
                    //Set argument
                    $msgarg = $cmd_arr["messageString"];
                    
                    //Check trigger type
                    switch ($cmd_arr["trigger"]){
                        //Help
                        case "help":
                            //Build help message listing commands
                            $msgcontent  = "Available commands are:\r\n";
                            $msgcontent .= "`.help` - This help message.\r\n";
                            $msgcontent .= "`.list` - List all game servers and their id number.\r\n";
                            $msgcontent .= "`.goto [id]` - Jump into an rcon session on server [id].\r\n";
                            $msgcontent .= "`.quit` - Leave rcon session on current server.\r\n";
                            
                            //Send message to channel
                            send_message($channelID,$fromID,$msgcontent);
                            break;
                        
                        //List servers
                        case "list":
                            //Start server list
                            $msgcontent  = "Server List:\r\n";
                            
                            //Loop through servers
                            foreach ($this->f3->get('gameserver_ips') as $serverid => $serverinfo){
                                $msgcontent .= "`ID: ".$serverid." → ".$serverinfo['name']."`\r\n";
                            }
                            
                            //Send message
                            send_message($channelID,$fromID,$msgcontent);
                            break;
                        
                        //Start rcon session
                        case "goto":
                            //Check if server id is valid
                            if(!$arguments[0] || !array_key_exists($arguments[0],$this->f3->get('gameserver_ips'))){
                                //Server id not found
                                $msgcontent  = "Invalid server id.\r\n";
                                
                                //Send message and exit
                                send_message($channelID,$fromID,$msgcontent);
                                return false;
                            }
                            
                            //Save server id
                            $serverid = $arguments[0];
                            
                            //Init sourcequery
                        	try{
                        		//Start rcon session to server
                        		$game_query->Connect($this->f3->get('gameserver_ips')[$serverid]['ip'], $this->f3->get('gameserver_ips')[$serverid]['port'], 1, xPaw\SourceQuery\SourceQuery::SOURCE);
                        		$game_query->SetRconPassword($this->f3->get('gameserver_ips')[$serverid]['password']);
                        		
                        		//Run test query
                        		$response = $game_query->Rcon('hostname');
                        		
                        		//Set serverid for session
                        		$curr_server = $serverid;
                        		
                        		//Set message
                        		$msgcontent = "🆗 You are now connected to RCON session on server ".$this->f3->get('gameserver_ips')[$serverid]['name']."!\r\n";
                        	}catch(Exception $e){
                        		//Error connecting!
                        		$this->logger->err($e->getMessage());
                        		
                        		//Set message
                        		$msgcontent = "❗ ️Error connecting to RCON session on server ".$this->f3->get('gameserver_ips')[$serverid]['name']."!\r\n";
                        	}
                            
                            //Close rcon session
                            $game_query->Disconnect();
                            
                            //Send message
                            send_message($channelID,$fromID,$msgcontent);
                            break;
                        
                        //Exit an rcon session
                        case "quit":
                            //Check if server session exists
                            if($curr_server){
                                //Server session exists
                                $msgcontent  = "Quit session on ".$this->f3->get('gameserver_ips')[$curr_server]['name']."\r\n";
                                
                                //Kill session
                                $curr_server = null;
                            }else{
                                //Currently not in server session
                                $msgcontent  = "⚠ Not in a server session, no need to quit!\r\n";
                            }
                            
                            //Send message
                            send_message($channelID,$fromID,$msgcontent);
                            break;
                    }
                //Normal chat message (rcon command)
                }else{
                    //Check if server session exists
                    if($curr_server){
                     	//Server session exists
                     	try{
                    		//Start rcon session to server
                    		$game_query->Connect($this->f3->get('gameserver_ips')[$curr_server]['ip'], $this->f3->get('gameserver_ips')[$curr_server]['port'], 1, xPaw\SourceQuery\SourceQuery::SOURCE);
                    		$game_query->SetRconPassword($this->f3->get('gameserver_ips')[$curr_server]['password']);
                    		
                    		//Run command
                    		$response = $game_query->Rcon($message);
                    		
                    		//Set message
                    		$msgcontent = $response;
                    		$codeblock = true;
                    	}catch(Exception $e){
                    		//Error connecting!
                    		$this->logger->err($e->getMessage());
                    		
                    		//Set message
                    		$msgcontent = "❗ ️Error sending rcon command to server ".$this->f3->get('gameserver_ips')[$serverid]['name']."!\r\n";
                    	}
                        
                        //Close rcon session
                        $game_query->Disconnect();
                    }else{
                        //Currently not in server session
                        $msgcontent = "⚠ ️You are currently not in a server session, type `.list` to list servers, or `.goto [id]` if you know the server ID.\r\n";
                        $codeblock = false;
                    }
                    
                    //Send message
                    send_message($channelID,$fromID,$msgcontent,$codeblock);
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