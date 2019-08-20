<?php
//Database connection string
$db = new DB\SQL(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $f3->get('db_host'), $f3->get('db_name')), $f3->get('db_user'), $f3->get('db_pass'), array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));