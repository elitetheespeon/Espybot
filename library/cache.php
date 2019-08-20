<?php
class prefcache{
    function search($search, $key){
        //Pull in globals
        global $logger,$f3,$db;

        //Check if search is null
        if(empty($search)){
            //Null search!
            return false;
        }
        
        //Check if more than one search param
        if(is_array($search)){
            //Start loop to check for searches against list
            if(!empty($f3->get($key))){
                foreach ($f3->get($key) as $value){
                    //Check for matches
                    if(count($search) == 2 && $search[0] == $value["value1"] && $search[1] == $value["value2"]){
                        //Search found in list
                        return true;
                    }elseif(count($search) == 3 && $search[0] == $value["value1"] && $search[1] == $value["value2"] && $search[2] == $value["value3"]){
                        //Search found in list
                        return true;                        
                    }
                }
            
            }else{
                //Cache is empty, pull in from DB
                $logger->info($key." cache empty, populating from DB.");
                $db_pull = $db->exec("SELECT `key`, `value1`, `value2`, `value3` FROM config WHERE `key` = ? AND bot_id = ?",array(1=>$key,2=>$f3->get('instance')));
                //Check if data was returned for database key
                if (count($db_pull) !== 0){
                    //Set key in cache
                    $f3->set($key,$db_pull);
                    //Start loop to check for search against list
                    foreach ($f3->get($key) as $value){
                        //Check for matches
                        if(count($search) == 2 && $search[0] == $value["value1"] && $search[1] == $value["value2"]){
                            //Search found in list
                            return true;
                        }elseif(count($search) == 3 && $search[0] == $value["value1"] && $search[1] == $value["value2"] && $search[2] == $value["value3"]){
                            //Search found in list
                            return true;                        
                        }
                    }
                }else{
                    //Nothing returned from DB, nothing to cache
                    $logger->info("Cache - No ".$key." entries found in database.");
                    return false;
                }
            }
        }else{
            //Start loop to check for search against list
            if(!empty($f3->get($key))){
                foreach ($f3->get($key) as $value){
                    //Check for matches
                    if($search == $value["value1"]){
                        //Search found in list
                        return true;
                    }
                }
            }else{
                //Cache is empty, pull in from DB
                $logger->info($key." cache empty, populating from DB.");
                $db_pull = $db->exec("SELECT `key`, `value1`, `value2`, `value3` FROM config WHERE `key` = ? AND bot_id = ?",array(1=>$key,2=>$f3->get('instance')));
                //Check if data was returned for database key
                if (count($db_pull) !== 0){
                    //Set key in cache
                    $f3->set($key,$db_pull);
                    //Start loop to check for search against list
                    foreach ($f3->get($key) as $value){
                        //Check for matches
                        if($search == $value["value1"]){
                            //Search found in list
                            return true;
                        }
                    }
                }else{
                    //Nothing returned from DB, nothing to cache
                    $logger->info("Cache - No ".$key." entries found in database.");
                    return false;
                }
            }
        }
        //If we didn't return true, search is not in list
        return false;
    }

    function add($key, $value1, $value2 = null, $value3 = null){
        //Pull in globals
        global $logger,$f3,$db;
        //Check for more than one value
        if(!empty($value1) && !empty($value2) && !empty($value3)){
            //Check if key already exists
            if($this->search(array($value1,$value2,$value3), $key)){
                //Key exists!
                return false;
            }else{
                //Key does not exist, add
                try{
                    $insert = $db->exec('INSERT INTO config (`key`, `value1`, `value2`, `value3`, `bot_id`) VALUES(?, ?, ?, ?, ?)',array(1=>$key,2=>$value1,3=>$value2,4=>$value3,5=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error adding ".$key." values in database.");
                    return false;
                }
                return true;
            }
        }elseif(!empty($value1) && !empty($value2)){
            //Check if key already exists
            if($this->search(array($value1,$value2), $key)){
                //Key exists!
                return false;
            }else{
                //Key does not exist, add
                try{
                    $insert = $db->exec('INSERT INTO config (`key`, `value1`, `value2`, `bot_id`) VALUES(?, ?, ?, ?)',array(1=>$key,2=>$value1,3=>$value2,4=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error adding ".$key." values in database.");
                    return false;
                }
                return true;
            }
        }elseif(!empty($value1)){
        //Check if key already exists
            if($this->search($value1, $key)){
                //Key exists!
                return false;
            }else{
                //Key does not exist, add
                try{
                    $insert = $db->exec('INSERT INTO config (`key`, `value1`, `bot_id`) VALUES(?, ?, ?)',array(1=>$key,2=>$value1,3=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error adding ".$key." values in database.");
                    return false;
                }
                return true;
            }
        }else{
            return false;
        }
    }

    function remove($key, $value1, $value2 = null, $value3 = null){
        //Pull in globals
        global $logger,$f3,$db;
        //Check for more than one value
        if(!empty($value1) && !empty($value2) && !empty($value3)){
            //Check if key already exists
            if($this->search(array($value1,$value2,$value3), $key)){
                //Key exists, remove
                try{
                    $delete = $db->exec('DELETE FROM config WHERE `key` = ? AND `value1` = ? AND `value2` = ? AND `value3` = ? AND bot_id = ?',array(1=>$key,2=>$value1,3=>$value2,4=>$value3,5=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error removing ".$key." values in database.");
                    return false;
                }
                return true;
            }else{
                //Key does not exist!
                return false;
            }
        }elseif(!empty($value1) && !empty($value2)){
            //Check if key already exists
            if($this->search(array($value1,$value2), $key)){
                //Key exists, remove
                try{
                    $delete = $db->exec('DELETE FROM config WHERE `key` = ? AND `value1` = ? AND `value2` = ? AND bot_id = ?',array(1=>$key,2=>$value1,3=>$value2,4=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error removing ".$key." values in database.");
                    return false;
                }
                return true;
            }else{
                //Key does not exist!
                return false;
            }
        }elseif(!empty($value1)){
            //Check if key already exists
            if($this->search($value1, $key)){
                //Key exists, remove
                try{
                    $delete = $db->exec('DELETE FROM config WHERE `key` = ? AND `value1` = ? AND bot_id = ?',array(1=>$key,2=>$value1,3=>$f3->get('instance')));
                    $this->sync($key);
                }catch(Exception $e){
                    $logger->info("Cache - Error removing ".$key." values in database.");
                    return false;
                }
                return true;
            }else{
                //Key does not exist!
                return false;
            }
        }else{
            return false;
        }
    }

    function exists($key){
        //Pull in globals
        global $logger,$f3;
        //Check if key is in cache
        if($f3->get($key)){
            //Key in cache
            return true;
        }else{
            //Key not in cache, sync
            $this->sync($key);
            //Check for key again
            if($f3->get($key)){
                //Found key in cache
                return true;
            }else{
                //Key not DB or cache
                return false;
            }
        }
    }

    function update($key,$value){
        //Pull in globals
        global $logger,$f3,$db;
        //Check if value is set for key
        if($f3->get($key) == $value){
            //Value is the same
            return true;
        }else{
            //Update key in cache
            $f3->set($key,array('value1' =>$value));
            //Update key in database
            try{
                $db_pull = $db->exec("UPDATE config SET `value1` = ? WHERE `key` = ? AND bot_id = ?",array(1=>$value,2=>$key,3=>$f3->get('instance')));
            }catch(Exception $e){
                $logger->err("Cache - Error updating ".$key." values in database.");
                return false;
            }
            return true;
        }
    }
    
    function sync($key){
        //Pull in globals
        global $logger,$f3,$db;
        //Start pull from DB
        try{
            $db_pull = $db->exec("SELECT `value1`, `value2`, `value3` FROM config WHERE `key` = ? AND bot_id = ?",array(1=>$key,2=>$f3->get('instance')));
        }catch(Exception $e){
            $logger->err("Cache - Error syncing ".$key." values in database.");
            return false;
        }
        //Check if data was returned for database key
        if (count($db_pull) !== 0){
            //Clear cache
            $f3->set($key,null);
            //Re-populate cache
            $f3->set($key,$db_pull);
            return true;
        }else{
            //Clear cache
            $f3->set($key,null);
            return false;
        }
    }
}