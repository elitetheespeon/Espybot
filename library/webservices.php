<?php
if($f3->get('webserver')){
    //Setup socket server and HTTP server
    $socket = new React\Socket\Server($discord->loop);
    $http = new React\Http\Server($socket);
    $result = new stdClass();
    
    //Callback for HTTP request
    $http->on('request', function ($request, $response) use ($result, $discord, $container, $route, $logger) {
        //Debug stuff
        $logger->debug('HTTP REQUEST GO');
        $logger->debug('HTTP connection recieved. Path: '.$request->getPath());
    
        //Get HTTP POST data
        $result->requestdata = $request->getPost();
        $result->requestbody = $request->getBody();
    
        //Overwrite server vars with HTTP request data
        $_SERVER['REMOTE_ADDR'] = $request->remoteAddress;
        $_SERVER['SCRIPT_URI'] = $request->getPath();
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['REQUEST_URI'] = $request->getPath();
    
        //Clear message var
        $result->message = '';
    
        //Initialize container
        $container = new League\Container\Container;
        
        //Feed HTTP request data into container
        $container->share('response', Zend\Diactoros\Response::class);
        $container->share('request', function () use ($request) {
            return Zend\Diactoros\ServerRequestFactory::fromGlobals(
                $_SERVER, array(), array(), array(), null
            );
        });
        
        //Emit response and start routing
        $container->share('emitter', Zend\Diactoros\Response\SapiEmitter::class);
        $route = new League\Route\RouteCollection($container);
        
        //Define route for sending messages
        $route->map('POST', '/api/sendmessage/{id:number}', function ($request,  $response,  $args) use ($result, $logger) {
            $logger->debug('Chat ID: '.$args['id']);
            //Check if message was sent
            if($result->requestdata['message']){
                //Send message to specified channel
                send_message($args['id'],null,$result->requestdata['message']);            
            }
            return $response;
        });
    
        //Define route for gitlab push
        $route->map('POST', '/api/gitlab/push', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('HTTP - Gitlab push');
            //Parse JSON into var
            $eventdata = json_decode($result->requestbody);
            //Check if JSON is valid
            if($eventdata && $eventdata->object_kind == 'push'){
                //Save out data we will use
                $push_username = $eventdata->user_name;
                $project_name = $eventdata->repository->name;
                $commits = $eventdata->commits;
    
                //Check if commit data is valid
                if(is_countable($commits) && count($commits) !== 0){
                    //Loop through each commit
                    foreach($commits as $commit){
                        //Send commit info to channel
                        $message = 'New commit pushed to **'.$eventdata->ref.'** on **'.$project_name.'** by **'.$push_username.'**'."\r\n";
                        $message .= '[**'.count($eventdata->added).'** files added : **'.count($eventdata->removed).'** files removed : **'.count($eventdata->modified).'** files modified]'."\r\n";
                        $message .= "```".$commit->message."```\r\n";
                        send_message('91930868951584768',null,$message);
                        send_message('147146956207030272',null,$message);
                    }
                }
            }
            //Return OK to gitlab
            $result->message = "OK";
            return $response;
        });
    
        //Define route for getting user info from username#NNNN
        $route->map('GET', '/api/resolve/username/{username}/{discriminator}', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Username resolver.');
            //Get info
            try{
                var_dump($args['username']);
                $userinfo = \Discord\Helpers\Guzzle::post(
                    'users/@me/relationships',
                    [
                        'username' => $args['username'],
                        'discriminator' => $args['discriminator'],
                    ]
                );
                $userinfo2 = \Discord\Helpers\Guzzle::get(
                    'users/@me/relationships'
                );
                var_dump($userinfo);
                var_dump($userinfo2);
            }catch (\Discord\Exceptions\DiscordRequestFailedException $e) {
                //error
                $userinfo = $e;
            }
            //JSON encode results
            $result->message = json_encode($userinfo);
            return $response;
        });
    
        //Define route for getting user info from user ID
        $route->map('GET', '/api/resolve/userid/{userid}', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('UserID resolver.');
            try{
                //Get user info
                $userinfo = find_member($args['userid']);
            }catch (\Discord\Exceptions\DiscordRequestFailedException $e) {
                //Error
                $userinfo = null;
            }
            //JSON encode results
            $result->message = json_encode($userinfo);
            return $response;
        });
    
        //Define route for getting complete guild list
        $route->map('GET', '/api/guildlist', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Guild list.');
            //Get list of guilds
            $guilds = $discord->guilds;
            $guildlist = array();
            foreach ($guilds as $guild){
                //Get guild info
                $guildlist[$guild->id]['id'] = $guild->id;
                $guildlist[$guild->id]['name'] = $guild->name;
                $guildlist[$guild->id]['owner_id'] = $guild->owner_id;
                $guildlist[$guild->id]['region'] = $guild->region;
                $guildlist[$guild->id]['joined_at'] = $guild->joined_at;
                $guildlist[$guild->id]['member_count'] = $guild->member_count;
                $guildlist[$guild->id]['icon'] = $guild->icon;
                $guildlist[$guild->id]['afk_timeout'] = $guild->afk_timeout;
            }
            //JSON encode results
            $result->message = json_encode($guildlist);
            return $response;
        });
    
        //Define route for getting complete channel list for all guilds
        $route->map('GET', '/api/channellist', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Channel list.');
            //Get list of guilds
            $guilds = $discord->guilds;
            $channellist = array();
            foreach ($guilds as $guild){
                //Get channel list
                $channels = $guild->channels;
                foreach ($channels as $channel){
                    //Get channel info
                    $channellist[$channel->id]['guild'] = $guild->name;
                    $channellist[$channel->id]['name'] = $channel->name;
                    $channellist[$channel->id]['id'] = $channel->id;
                    $channellist[$channel->id]['type'] = $channel->type;
                }
            }
            //JSON encode results
            $result->message = json_encode($channellist);
            return $response;
        });
        
        //Define route for getting complete user list for all guilds
        $route->map('GET', '/api/userlist', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Userlist.');
            //Get list of guilds
            $guilds = $discord->guilds;
            $userlist = array();
            foreach ($guilds as $guild){
                //Get member info
                $members = $guild->members;
                foreach ($members as $member){
                    //Get user info
                    $userlist[$guild->name][$member->id]['guild'] = $guild->name;
                    $userlist[$guild->name][$member->id]['username'] = $member->username;
                    $userlist[$guild->name][$member->id]['userid'] = $member->id;
                    $userlist[$guild->name][$member->id]['status'] = $member->status;
                }
            }
            //JSON encode results
            $result->message = json_encode($userlist);
            return $response;
        });
    
        //Define route for kicking user
        $route->map('GET', '/api/kick/{userid}/{guild}', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Kick user.');
            //Get guild id
            $guild = $discord->guilds->get('id',$args['guild']);
            //Check for valid guild
            if($guild){
                //Get user id
                $member = $guild->members->get('id',$args['userid']);
                if($member){
                    try{
                        //Attempt to kick user
                        $result = $member->kick();
                        $errorinfo = 'Success';
                    }catch(Exception $e){
                        //Error in kick
                        $error = true;
                        $errorinfo = 'Error Kicking User';
                    }
                }else{
                    //Invalid userid
                    $error = true;
                    $errorinfo = 'Invalid User ID';
                }
            }else{
                //Invalid guild
                $error = true;
                $errorinfo = 'Invalid Guild';
            }
            //Send result
            $result->message = json_encode($errorinfo);
            return $response;
        });
    
        //Define route for banning user
        $route->map('GET', '/api/ban/{userid}/{guild}', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Ban user.');
            //Get guild id
            $guild = $discord->guilds->get('id',$args['guild']);
            //Check for valid guild
            if($guild){
                //Get user id
                $member = $guild->members->get('id',$args['userid']);
                if($member){
                    try{
                        //Attempt to ban user
                        $result = $member->ban();
                        $errorinfo = 'Success';
                    }catch(Exception $e){
                        //Error in kick
                        $error = true;
                        $errorinfo = 'Error Banning User';
                    }
                }else{
                    //Invalid userid
                    $error = true;
                    $errorinfo = 'Invalid User ID';
                }
            }else{
                //Invalid guild
                $error = true;
                $errorinfo = 'Invalid Guild';
            }
            //Send result
            $result->message = json_encode($errorinfo);
            return $response;
        });
    
        //Define route for getting a user's info
        $route->map('GET', '/api/userinfo/{id}', function ($request,  $response,  $args) use ($result, $discord, $logger) {
            $logger->debug('Userinfo.');
            //Get list of guilds
            $guilds = $discord->guilds;
            $userdata = array();
            //Check if guilds exist
            if (is_countable($guilds) && count($guilds) !== 0){
                //Get guild info
                $guildinfo = get_all_member_guilds($args['id']);
                foreach($guildinfo as $guild){
                    //Store member data in array
                    $userdata[$args['id']][$guild->guild_id] = $guild;                                        
                }
            }

            //Check if user is valid
            if ($userdata[$args['id']]){
                //Check if user is in more than one guild
                $user_key = key($userdata[$args['id']]);
                $user_id = $args['id'];
                //Check how many guilds user is in
                if (is_countable($userdata[$args['id']]) && count($userdata[$args['id']]) > 0){
                    //User found in at least one guild
                    $member_guilds = array();
                    $i = 0;
                    //Loop through guild specific info
                    foreach($userdata[$args['id']] as $member){
                        //Save guild info
                        $member_guilds[$i]['guild_name'] = $discord->guilds->get('id', $member->guild_id)->getCreatableAttributes()['name'];
                        $member_guilds[$i]['guild_id'] = $member->guild_id;
                        $member_guilds[$i]['joined_at'] = $member->joined_at;
                        $member_guilds[$i]['mute'] = $member->mute;
                        $member_guilds[$i]['deaf'] = $member->deaf;
                        try{
                            //Check if roles exist
                            if($member->roles){
                                //Get user roles
                                $member_guilds[$i]['roles'] = $member->roles;
                            }
                        }catch(Discord\Exceptions\Rest\NoPermissionsException $e){
                            //We don't have permission to get user roles
                            $logger->warn('No permission to get roles for user '.$member->info->username);                    
                        }
                        $i++;
                    }
                    $servers = '';
                    $i = 0;
                    //Loop through guilds and build list of names
                    if(!empty($member_guilds)){
                        foreach($member_guilds as $guild){
                            $i++;
                            if(is_countable($member_guilds) && $i == count($member_guilds)){
                                $servers .= $guild['guild_name'];
                            }else{
                                $servers .= $guild['guild_name'].", ";
                            }
                        }                
                    }
                    //Build final user object and fill with data
                    $userdata_clean = new stdClass();
                    $userdata_clean->user = $userdata[$user_id][$user_key]->user;
                    $userdata_clean->status = $userdata[$user_id][$user_key]->status;
                    $userdata_clean->game = $userdata[$user_id][$user_key]->game;
                    $userdata_clean->servers = $servers;
                    $userdata_clean->serverinfo = $member_guilds;
                    try{
                        //Add roles
                        if($userdata[$user_id][$user_key]->roles){
                            //var_dump($userdata[$user_id][$user_key]->roles);
                            //$userdata_clean->roles = $userdata[$user_id][$user_key]->roles;
                        }
                    }catch(Discord\Exceptions\Rest\NoPermissionsException $e){
                        //We don't have permission to get user roles
                        $logger->warn('No permission to get roles for user '.$member->info->username);                    
                    }                    
                }else{
                    //User not in any guilds
                    $userdata_clean = null;
                }
            }else{
                //User not found
                $userdata_clean = null;
            }
    
            //JSON encode results
            $result->message = json_encode($userdata_clean);
            return $response;
        });
    
        try{    
            //Dispatch route
            $response1 = $route->dispatch($container->get('request'), $container->get('response'));
            $matched = true;
        }catch(League\Route\Http\Exception\NotFoundException $e){
            //No route matched
            $logger->err('Unmatched route.');
            $matched = false;
        }
    
        //Check if error was given
        if($error){
            //Send Error back and close socket
            $response->writeHead(500, array('Content-Type' => 'text/html'));
            $response->end($result->message);
            $result->message = '';
            $result->requestData = '';
            $result->requestbody = '';
        }else{
            //Check if route was matched    
            if($matched){
                //Send JSON back and close socket
                $response->writeHead(200, array('Content-Type' => 'application/json'));
                $response->end($result->message);
                $result->message = '';
                $result->requestData = '';
                $result->requestbody = '';
            }else{
                //Give a 404 and close socket
                $response->writeHead(404, array('Content-Type' => 'text/html'));
                $response->end('Page not found');
                $result->message = '';
                $result->requestData = '';
                $result->requestbody = '';
            }
        }
    });
    
    //Setup listener for HTTP connections
    $socket->listen($f3->get('webserver_port'), '0.0.0.0');
}