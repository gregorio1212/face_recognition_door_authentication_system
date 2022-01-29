<?php

// database is located in the same server, if database is in another server change the ip here
$dbHost = "localhost";
$dbUser = "your_user"; 
$dbPasswd = "your_password";
$dbName = "your_database_name";

try{
    $db = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPasswd);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOEXCEPTION $e){
    echo $e->getMessage();
}